// Core ERP Types

export interface User {
  id: string;
  name: string;
  email: string;
  role: UserRole;
  department?: string;
  createdAt: Date;
  updatedAt: Date;
  isActive: boolean;
}

export enum UserRole {
  ADMIN = 'admin',
  MANAGER = 'manager',
  EMPLOYEE = 'employee',
  VIEWER = 'viewer'
}

export interface DataFlow {
  id: string;
  source: string;
  destination: string;
  dataType: string;
  volume: number;
  timestamp: Date;
  status: DataFlowStatus;
}

export enum DataFlowStatus {
  ACTIVE = 'active',
  PENDING = 'pending',
  FAILED = 'failed',
  COMPLETED = 'completed'
}

export interface SystemIntegration {
  id: string;
  name: string;
  type: IntegrationType;
  endpoint: string;
  isActive: boolean;
  lastSync?: Date;
  configuration: Record<string, any>;
}

export enum IntegrationType {
  API = 'api',
  DATABASE = 'database',
  FILE_SYSTEM = 'file_system',
  WEBHOOK = 'webhook'
}

export interface DashboardMetrics {
  totalUsers: number;
  activeDataFlows: number;
  systemIntegrations: number;
  dataVolumeToday: number;
  successRate: number;
}