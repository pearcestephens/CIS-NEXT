'use client'

import { useEffect, useState } from 'react'
import { DataFlow, DataFlowStatus } from '@/types'

export function DataFlowVisualization() {
  const [dataFlows, setDataFlows] = useState<DataFlow[]>([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    // Mock API call for data flows
    const fetchDataFlows = async () => {
      try {
        await new Promise(resolve => setTimeout(resolve, 800))
        
        const mockDataFlows: DataFlow[] = [
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
        
        setDataFlows(mockDataFlows)
      } catch (error) {
        console.error('Error fetching data flows:', error)
      } finally {
        setLoading(false)
      }
    }

    fetchDataFlows()
  }, [])

  const getStatusColor = (status: DataFlowStatus) => {
    switch (status) {
      case DataFlowStatus.ACTIVE:
        return 'bg-green-100 text-green-800'
      case DataFlowStatus.COMPLETED:
        return 'bg-blue-100 text-blue-800'
      case DataFlowStatus.PENDING:
        return 'bg-yellow-100 text-yellow-800'
      case DataFlowStatus.FAILED:
        return 'bg-red-100 text-red-800'
      default:
        return 'bg-gray-100 text-gray-800'
    }
  }

  if (loading) {
    return (
      <div className="card">
        <h2 className="text-xl font-semibold mb-4">Data Flow Monitoring</h2>
        <div className="space-y-4">
          {[...Array(4)].map((_, i) => (
            <div key={i} className="animate-pulse">
              <div className="h-16 bg-gray-200 rounded"></div>
            </div>
          ))}
        </div>
      </div>
    )
  }

  return (
    <div className="card">
      <h2 className="text-xl font-semibold mb-4">Data Flow Monitoring</h2>
      <div className="space-y-4">
        {dataFlows.map((flow) => (
          <div key={flow.id} className="border rounded-lg p-4 hover:bg-gray-50 transition-colors">
            <div className="flex items-center justify-between mb-2">
              <div className="flex items-center space-x-2">
                <span className="font-medium text-gray-900">{flow.source}</span>
                <span className="text-gray-500">→</span>
                <span className="font-medium text-gray-900">{flow.destination}</span>
              </div>
              <span className={`px-2 py-1 rounded-full text-xs font-medium ${getStatusColor(flow.status)}`}>
                {flow.status}
              </span>
            </div>
            <div className="flex items-center justify-between text-sm text-gray-600">
              <div className="flex items-center space-x-4">
                <span>Type: {flow.dataType}</span>
                <span>Volume: {flow.volume.toLocaleString()} records</span>
              </div>
              <span>
                {flow.timestamp.toLocaleTimeString()}
              </span>
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}