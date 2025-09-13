'use client'

import { useEffect, useState } from 'react'
import { DashboardMetrics } from '@/types'

export function DashboardStats() {
  const [metrics, setMetrics] = useState<DashboardMetrics | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    // Mock API call - in real app would fetch from API endpoint
    const fetchMetrics = async () => {
      try {
        // Simulate API delay
        await new Promise(resolve => setTimeout(resolve, 1000))
        
        const mockMetrics: DashboardMetrics = {
          totalUsers: 15,
          activeDataFlows: 8,
          systemIntegrations: 4,
          dataVolumeToday: 2690,
          successRate: 96.5,
        }
        
        setMetrics(mockMetrics)
      } catch (error) {
        console.error('Error fetching metrics:', error)
      } finally {
        setLoading(false)
      }
    }

    fetchMetrics()
  }, [])

  if (loading) {
    return (
      <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
        {[...Array(4)].map((_, i) => (
          <div key={i} className="card animate-pulse">
            <div className="h-4 bg-gray-200 rounded mb-2"></div>
            <div className="h-8 bg-gray-200 rounded"></div>
          </div>
        ))}
      </div>
    )
  }

  if (!metrics) {
    return (
      <div className="card">
        <p className="text-red-600">Failed to load dashboard metrics</p>
      </div>
    )
  }

  return (
    <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
      <div className="card">
        <h3 className="text-sm font-medium text-gray-500 mb-2">Total Users</h3>
        <p className="text-3xl font-bold text-gray-900">{metrics.totalUsers}</p>
      </div>
      
      <div className="card">
        <h3 className="text-sm font-medium text-gray-500 mb-2">Active Data Flows</h3>
        <p className="text-3xl font-bold text-green-600">{metrics.activeDataFlows}</p>
      </div>
      
      <div className="card">
        <h3 className="text-sm font-medium text-gray-500 mb-2">System Integrations</h3>
        <p className="text-3xl font-bold text-blue-600">{metrics.systemIntegrations}</p>
      </div>
      
      <div className="card">
        <h3 className="text-sm font-medium text-gray-500 mb-2">Success Rate</h3>
        <p className="text-3xl font-bold text-primary-600">{metrics.successRate}%</p>
      </div>
    </div>
  )
}