import { DataFlow, DataFlowStatus } from '@/types'

// Mock data flows for demonstration
let dataFlows: DataFlow[] = [
  {
    id: '1',
    source: 'CRM System',
    destination: 'Analytics Dashboard',
    dataType: 'Customer Data',
    volume: 1520,
    timestamp: new Date(),
    status: DataFlowStatus.ACTIVE,
  },
  {
    id: '2',
    source: 'Inventory System',
    destination: 'Reporting Module',
    dataType: 'Product Data',
    volume: 850,
    timestamp: new Date(Date.now() - 3600000),
    status: DataFlowStatus.COMPLETED,
  },
  {
    id: '3',
    source: 'HR System',
    destination: 'Payroll System',
    dataType: 'Employee Data',
    volume: 320,
    timestamp: new Date(Date.now() - 1800000),
    status: DataFlowStatus.PENDING,
  },
  {
    id: '4',
    source: 'External API',
    destination: 'Data Warehouse',
    dataType: 'Market Data',
    volume: 0,
    timestamp: new Date(Date.now() - 7200000),
    status: DataFlowStatus.FAILED,
  }
]

export class DataFlowModel {
  static async findAll(): Promise<DataFlow[]> {
    return dataFlows
  }

  static async findById(id: string): Promise<DataFlow | null> {
    return dataFlows.find(flow => flow.id === id) || null
  }

  static async findByStatus(status: DataFlowStatus): Promise<DataFlow[]> {
    return dataFlows.filter(flow => flow.status === status)
  }

  static async getActiveFlows(): Promise<DataFlow[]> {
    return dataFlows.filter(flow => flow.status === DataFlowStatus.ACTIVE)
  }

  static async getTotalVolumeToday(): Promise<number> {
    const today = new Date()
    today.setHours(0, 0, 0, 0)
    
    return dataFlows
      .filter(flow => flow.timestamp >= today)
      .reduce((total, flow) => total + flow.volume, 0)
  }

  static async getSuccessRate(): Promise<number> {
    const total = dataFlows.length
    const successful = dataFlows.filter(flow => 
      flow.status === DataFlowStatus.COMPLETED || flow.status === DataFlowStatus.ACTIVE
    ).length
    
    return total > 0 ? (successful / total) * 100 : 0
  }

  static async create(flowData: Omit<DataFlow, 'id'>): Promise<DataFlow> {
    const newFlow: DataFlow = {
      ...flowData,
      id: (dataFlows.length + 1).toString(),
    }
    dataFlows.push(newFlow)
    return newFlow
  }

  static async updateStatus(id: string, status: DataFlowStatus): Promise<DataFlow | null> {
    const flowIndex = dataFlows.findIndex(flow => flow.id === id)
    if (flowIndex === -1) return null

    dataFlows[flowIndex].status = status
    dataFlows[flowIndex].timestamp = new Date()
    return dataFlows[flowIndex]
  }
}