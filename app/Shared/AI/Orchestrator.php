<?php
/**
 * AI Orchestrator - Event-driven AI pipeline management
 * File: app/Shared/AI/Orchestrator.php
 * Author: CIS Developer Bot
 * Created: 2025-09-11
 * Purpose: Event-bus driven orchestration for chaining AI calls
 */

namespace App\Shared\AI;

use App\Integrations\OpenAI\Client as OpenAIClient;
use App\Integrations\Claude\Client as ClaudeClient;
use App\Shared\AI\Events;
use Exception;

class Orchestrator {
    private array $config;
    private string $mode;
    
    // Job status constants
    const STATUS_PENDING = 'pending';
    const STATUS_RUNNING = 'running';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';
    
    public function __construct() {
        $this->config = include __DIR__ . '/../../../config/ai.php';
        $this->mode = $this->config['orchestration']['mode'] ?? 'bus';
    }
    
    /**
     * Execute single AI operation
     */
    public function executeSingle(string $provider, string $operation, array $request_data, array $options = []): array {
        $job_id = Events::generateJobId();
        
        try {
            // Create orchestration job
            $job = $this->createOrchestrationJob(Events::JOB_TYPE_SINGLE, [
                'provider' => $provider,
                'operation' => $operation,
                'options' => $options
            ], $request_data);
            
            // Create and store event
            $event = Events::createEvent($job_id, $provider, $operation, $request_data);
            $event_id = $this->storeEvent($event);
            
            // Update job status
            $this->updateJobStatus($job_id, self::STATUS_RUNNING);
            
            // Execute the operation
            $result = $this->executeOperation($provider, $operation, $request_data, $options);
            
            // Complete the event
            $completion = Events::createEventCompletion(
                $result['data'] ?? [],
                $result['tokens_used'] ?? 0,
                $result['response_time_ms'] ?? 0,
                $result['success'] ? null : ($result['error'] ?? 'Unknown error')
            );
            
            $this->completeEvent($event_id, $completion);
            
            // Update job
            $job_status = $result['success'] ? self::STATUS_COMPLETED : self::STATUS_FAILED;
            $this->updateJobStatus($job_id, $job_status, $result);
            
            return [
                'success' => $result['success'],
                'job_id' => $job_id,
                'event_id' => $event_id,
                'result' => $result,
                'orchestration' => [
                    'type' => Events::JOB_TYPE_SINGLE,
                    'status' => $job_status
                ]
            ];
            
        } catch (Exception $e) {
            $this->updateJobStatus($job_id, self::STATUS_FAILED, ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'job_id' => $job_id,
                'error' => $e->getMessage(),
                'orchestration' => [
                    'type' => Events::JOB_TYPE_SINGLE,
                    'status' => self::STATUS_FAILED
                ]
            ];
        }
    }
    
    /**
     * Execute chained AI operations (A → B → C)
     */
    public function executeChain(array $chain_config): array {
        $job_id = Events::generateJobId();
        
        try {
            // Create orchestration job
            $job = $this->createOrchestrationJob(Events::JOB_TYPE_CHAIN, $chain_config, []);
            
            $this->updateJobStatus($job_id, self::STATUS_RUNNING);
            
            $chain_results = [];
            $current_input = $chain_config['initial_input'] ?? [];
            $parent_event_id = null;
            
            foreach ($chain_config['steps'] as $step_index => $step) {
                try {
                    // Create event for this step
                    $event = Events::createEvent(
                        $job_id,
                        $step['provider'],
                        $step['operation'],
                        $current_input,
                        $step['model'] ?? null,
                        $parent_event_id
                    );
                    
                    $event_id = $this->storeEvent($event);
                    
                    // Execute the step
                    $step_result = $this->executeOperation(
                        $step['provider'],
                        $step['operation'],
                        $current_input,
                        $step['options'] ?? []
                    );
                    
                    // Complete the event
                    $completion = Events::createEventCompletion(
                        $step_result['data'] ?? [],
                        $step_result['tokens_used'] ?? 0,
                        $step_result['response_time_ms'] ?? 0,
                        $step_result['success'] ? null : ($step_result['error'] ?? 'Step failed')
                    );
                    
                    $this->completeEvent($event_id, $completion);
                    
                    if (!$step_result['success']) {
                        if ($chain_config['continue_on_error'] ?? false) {
                            $chain_results[] = [
                                'step' => $step_index,
                                'success' => false,
                                'error' => $step_result['error']
                            ];
                            continue;
                        } else {
                            throw new Exception("Chain step {$step_index} failed: " . $step_result['error']);
                        }
                    }
                    
                    $chain_results[] = [
                        'step' => $step_index,
                        'success' => true,
                        'result' => $step_result,
                        'event_id' => $event_id
                    ];
                    
                    // Prepare input for next step
                    $current_input = $this->extractChainOutput($step_result, $step['output_mapping'] ?? null);
                    $parent_event_id = $event_id;
                    
                } catch (Exception $e) {
                    if (!($chain_config['continue_on_error'] ?? false)) {
                        throw $e;
                    }
                    
                    $chain_results[] = [
                        'step' => $step_index,
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            $this->updateJobStatus($job_id, self::STATUS_COMPLETED, [
                'steps_completed' => count($chain_results),
                'final_output' => $current_input
            ]);
            
            return [
                'success' => true,
                'job_id' => $job_id,
                'chain_results' => $chain_results,
                'final_output' => $current_input,
                'orchestration' => [
                    'type' => Events::JOB_TYPE_CHAIN,
                    'status' => self::STATUS_COMPLETED,
                    'steps_completed' => count($chain_results)
                ]
            ];
            
        } catch (Exception $e) {
            $this->updateJobStatus($job_id, self::STATUS_FAILED, ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'job_id' => $job_id,
                'error' => $e->getMessage(),
                'chain_results' => $chain_results ?? [],
                'orchestration' => [
                    'type' => Events::JOB_TYPE_CHAIN,
                    'status' => self::STATUS_FAILED
                ]
            ];
        }
    }
    
    /**
     * Execute fan-out operations (parallel execution)
     */
    public function executeFanOut(array $fanout_config): array {
        $job_id = Events::generateJobId();
        
        try {
            // Create orchestration job
            $job = $this->createOrchestrationJob(Events::JOB_TYPE_FANOUT, $fanout_config, []);
            
            $this->updateJobStatus($job_id, self::STATUS_RUNNING);
            
            $parallel_results = [];
            $event_ids = [];
            
            // Start all operations
            foreach ($fanout_config['operations'] as $op_index => $operation) {
                $event = Events::createEvent(
                    $job_id,
                    $operation['provider'],
                    $operation['operation'],
                    $operation['input'],
                    $operation['model'] ?? null
                );
                
                $event_id = $this->storeEvent($event);
                $event_ids[] = $event_id;
                
                // In a real implementation, these would run in parallel
                // For now, we'll simulate parallel execution sequentially
                $result = $this->executeOperation(
                    $operation['provider'],
                    $operation['operation'],
                    $operation['input'],
                    $operation['options'] ?? []
                );
                
                $completion = Events::createEventCompletion(
                    $result['data'] ?? [],
                    $result['tokens_used'] ?? 0,
                    $result['response_time_ms'] ?? 0,
                    $result['success'] ? null : ($result['error'] ?? 'Operation failed')
                );
                
                $this->completeEvent($event_id, $completion);
                
                $parallel_results[] = [
                    'operation_index' => $op_index,
                    'event_id' => $event_id,
                    'success' => $result['success'],
                    'result' => $result['success'] ? $result : null,
                    'error' => $result['success'] ? null : $result['error']
                ];
            }
            
            // Check if all operations completed successfully
            $all_success = true;
            foreach ($parallel_results as $result) {
                if (!$result['success']) {
                    $all_success = false;
                    break;
                }
            }
            
            $job_status = $all_success ? self::STATUS_COMPLETED : self::STATUS_FAILED;
            $this->updateJobStatus($job_id, $job_status, [
                'operations_completed' => count($parallel_results),
                'success_count' => count(array_filter($parallel_results, fn($r) => $r['success']))
            ]);
            
            return [
                'success' => $all_success,
                'job_id' => $job_id,
                'parallel_results' => $parallel_results,
                'orchestration' => [
                    'type' => Events::JOB_TYPE_FANOUT,
                    'status' => $job_status,
                    'operations_completed' => count($parallel_results)
                ]
            ];
            
        } catch (Exception $e) {
            $this->updateJobStatus($job_id, self::STATUS_FAILED, ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'job_id' => $job_id,
                'error' => $e->getMessage(),
                'orchestration' => [
                    'type' => Events::JOB_TYPE_FANOUT,
                    'status' => self::STATUS_FAILED
                ]
            ];
        }
    }
    
    /**
     * Execute fan-in operations (aggregate results)
     */
    public function executeFanIn(array $fanin_config): array {
        $job_id = Events::generateJobId();
        
        try {
            // Get input events/results
            $input_results = $fanin_config['input_results'] ?? [];
            $aggregation_method = $fanin_config['aggregation_method'] ?? 'concat';
            
            // Create orchestration job
            $job = $this->createOrchestrationJob(Events::JOB_TYPE_FANIN, $fanin_config, $input_results);
            
            $this->updateJobStatus($job_id, self::STATUS_RUNNING);
            
            // Aggregate results based on method
            $aggregated_result = $this->aggregateResults($input_results, $aggregation_method);
            
            $this->updateJobStatus($job_id, self::STATUS_COMPLETED, [
                'aggregation_method' => $aggregation_method,
                'input_count' => count($input_results),
                'output' => $aggregated_result
            ]);
            
            return [
                'success' => true,
                'job_id' => $job_id,
                'aggregated_result' => $aggregated_result,
                'orchestration' => [
                    'type' => Events::JOB_TYPE_FANIN,
                    'status' => self::STATUS_COMPLETED,
                    'aggregation_method' => $aggregation_method
                ]
            ];
            
        } catch (Exception $e) {
            $this->updateJobStatus($job_id, self::STATUS_FAILED, ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'job_id' => $job_id,
                'error' => $e->getMessage(),
                'orchestration' => [
                    'type' => Events::JOB_TYPE_FANIN,
                    'status' => self::STATUS_FAILED
                ]
            ];
        }
    }
    
    /**
     * Get orchestration job status
     */
    public function getJobStatus(string $job_id): array {
        global $mysqli;
        
        try {
            $sql = "SELECT * FROM cis_ai_orchestration_jobs WHERE job_id = ?";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('s', $job_id);
            $stmt->execute();
            
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                // Get associated events
                $events_sql = "SELECT * FROM cis_ai_events WHERE job_id = ? ORDER BY created_at";
                $events_stmt = $mysqli->prepare($events_sql);
                $events_stmt->bind_param('s', $job_id);
                $events_stmt->execute();
                
                $events_result = $events_stmt->get_result();
                $events = $events_result->fetch_all(MYSQLI_ASSOC);
                
                return [
                    'success' => true,
                    'job' => $row,
                    'events' => $events
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Job not found'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Execute individual operation based on provider
     */
    private function executeOperation(string $provider, string $operation, array $input, array $options = []): array {
        $start_time = microtime(true);
        
        try {
            switch ($provider) {
                case Events::PROVIDER_OPENAI:
                    $client = new OpenAIClient();
                    break;
                case Events::PROVIDER_CLAUDE:
                    $client = new ClaudeClient();
                    break;
                default:
                    throw new Exception("Unknown provider: {$provider}");
            }
            
            switch ($operation) {
                case Events::OP_CHAT:
                    $result = $client->chat($input['messages'] ?? [], $options);
                    break;
                case Events::OP_EMBEDDING:
                    if ($provider === Events::PROVIDER_OPENAI) {
                        $result = $client->embedding($input['text'] ?? '', $options['model'] ?? null);
                    } else {
                        throw new Exception("Embedding not supported for provider: {$provider}");
                    }
                    break;
                default:
                    throw new Exception("Unknown operation: {$operation}");
            }
            
            $response_time = round((microtime(true) - $start_time) * 1000);
            $result['response_time_ms'] = $response_time;
            
            return $result;
            
        } catch (Exception $e) {
            $response_time = round((microtime(true) - $start_time) * 1000);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'response_time_ms' => $response_time
            ];
        }
    }
    
    /**
     * Extract output for chain step
     */
    private function extractChainOutput(array $step_result, ?array $output_mapping = null): array {
        if ($output_mapping) {
            $output = [];
            foreach ($output_mapping as $source_key => $target_key) {
                if (isset($step_result['data'][$source_key])) {
                    $output[$target_key] = $step_result['data'][$source_key];
                }
            }
            return $output;
        }
        
        // Default: pass through the main content
        if (isset($step_result['data']['choices'][0]['message']['content'])) {
            return ['messages' => [['role' => 'user', 'content' => $step_result['data']['choices'][0]['message']['content']]]];
        }
        
        if (isset($step_result['content'])) {
            return ['messages' => [['role' => 'user', 'content' => $step_result['content']]]];
        }
        
        return $step_result['data'] ?? [];
    }
    
    /**
     * Aggregate results based on method
     */
    private function aggregateResults(array $results, string $method): array {
        switch ($method) {
            case 'concat':
                $concatenated = '';
                foreach ($results as $result) {
                    if (isset($result['content'])) {
                        $concatenated .= $result['content'] . "\n\n";
                    }
                }
                return ['aggregated_content' => trim($concatenated)];
                
            case 'merge':
                $merged = [];
                foreach ($results as $result) {
                    if (is_array($result)) {
                        $merged = array_merge($merged, $result);
                    }
                }
                return $merged;
                
            case 'vote':
                // Simple voting mechanism
                $votes = [];
                foreach ($results as $result) {
                    $content = $result['content'] ?? json_encode($result);
                    $votes[$content] = ($votes[$content] ?? 0) + 1;
                }
                arsort($votes);
                return ['voted_result' => array_key_first($votes), 'votes' => $votes];
                
            default:
                return ['all_results' => $results];
        }
    }
    
    /**
     * Create orchestration job record
     */
    private function createOrchestrationJob(string $job_type, array $pipeline_config, array $input_data): string {
        global $mysqli;
        
        $job_id = Events::generateJobId();
        
        $sql = "INSERT INTO cis_ai_orchestration_jobs 
                (job_id, job_type, pipeline_config, input_data, status, created_by) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('sssssi',
            $job_id,
            $job_type,
            json_encode($pipeline_config),
            json_encode($input_data),
            self::STATUS_PENDING,
            $created_by = 1 // Default user
        );
        $stmt->execute();
        
        return $job_id;
    }
    
    /**
     * Store AI event in database
     */
    private function storeEvent(array $event): int {
        global $mysqli;
        
        $sql = "INSERT INTO cis_ai_events 
                (job_id, trace_id, parent_event_id, provider, model, operation, request_data, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('sssissss',
            $event['job_id'],
            $event['trace_id'],
            $event['parent_event_id'],
            $event['provider'],
            $event['model'],
            $event['operation'],
            json_encode($event['request_data']),
            $event['status']
        );
        $stmt->execute();
        
        return $mysqli->insert_id;
    }
    
    /**
     * Complete AI event with results
     */
    private function completeEvent(int $event_id, array $completion): void {
        global $mysqli;
        
        $sql = "UPDATE cis_ai_events 
                SET response_data = ?, status = ?, error_message = ?, tokens_used = ?, response_time_ms = ?, completed_at = ? 
                WHERE id = ?";
        
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('sssiisi',
            $completion['response_data'] ? json_encode($completion['response_data']) : null,
            $completion['status'],
            $completion['error_message'],
            $completion['tokens_used'],
            $completion['response_time_ms'],
            $completion['completed_at'],
            $event_id
        );
        $stmt->execute();
    }
    
    /**
     * Update orchestration job status
     */
    private function updateJobStatus(string $job_id, string $status, ?array $output_data = null): void {
        global $mysqli;
        
        $sql = "UPDATE cis_ai_orchestration_jobs SET status = ?, output_data = ?";
        
        if ($status === self::STATUS_RUNNING) {
            $sql .= ", started_at = NOW()";
        } elseif (in_array($status, [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED])) {
            $sql .= ", completed_at = NOW()";
        }
        
        $sql .= " WHERE job_id = ?";
        
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('sss',
            $status,
            $output_data ? json_encode($output_data) : null,
            $job_id
        );
        $stmt->execute();
    }
}
