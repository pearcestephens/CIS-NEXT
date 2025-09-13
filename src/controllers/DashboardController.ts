import { UserModel } from '@/models/User'
import { DataFlowModel } from '@/models/DataFlow'
import { DashboardMetrics, DataFlowStatus } from '@/types'

export class DashboardController {
  static async getDashboardMetrics(): Promise<DashboardMetrics> {
    try {
      const [
        users,
        activeFlows,
        dataVolumeToday,
        successRate
      ] = await Promise.all([
        UserModel.findAll(),
        DataFlowModel.findByStatus(DataFlowStatus.ACTIVE),
        DataFlowModel.getTotalVolumeToday(),
        DataFlowModel.getSuccessRate()
      ])

      return {
        totalUsers: users.length,
        activeDataFlows: activeFlows.length,
        systemIntegrations: 4, // Mock data - in real system would query integrations
        dataVolumeToday,
        successRate: Math.round(successRate * 100) / 100, // Round to 2 decimal places
      }
    } catch (error) {
      console.error('Error fetching dashboard metrics:', error)
      throw new Error('Failed to fetch dashboard metrics')
    }
  }

  static async getRecentDataFlows(limit: number = 10) {
    try {
      const flows = await DataFlowModel.findAll()
      return flows
        .sort((a, b) => b.timestamp.getTime() - a.timestamp.getTime())
        .slice(0, limit)
    } catch (error) {
      console.error('Error fetching recent data flows:', error)
      throw new Error('Failed to fetch recent data flows')
    }
  }

  static async getSystemHealth() {
    try {
      const flows = await DataFlowModel.findAll()
      const activeFlows = flows.filter(f => f.status === DataFlowStatus.ACTIVE)
      const failedFlows = flows.filter(f => f.status === DataFlowStatus.FAILED)
      
      return {
        status: failedFlows.length === 0 ? 'healthy' : 'warning',
        activeConnections: activeFlows.length,
        failedConnections: failedFlows.length,
        uptime: '99.9%', // Mock data
        lastCheck: new Date(),
      }
    } catch (error) {
      console.error('Error checking system health:', error)
      throw new Error('Failed to check system health')
    }
  }
}