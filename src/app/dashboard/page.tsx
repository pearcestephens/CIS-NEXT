import { DashboardStats } from '@/components/DashboardStats'
import { DataFlowVisualization } from '@/components/DataFlowVisualization'
import Link from 'next/link'

export default function DashboardPage() {
  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-3xl font-bold text-gray-900">Dashboard</h1>
        <div className="flex space-x-3">
          <Link href="/users" className="btn-primary">
            Manage Users
          </Link>
          <Link href="/analytics" className="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg transition-colors">
            View Analytics
          </Link>
        </div>
      </div>

      {/* Key Metrics */}
      <DashboardStats />

      {/* Data Flow Monitoring */}
      <DataFlowVisualization />

      {/* Quick Actions Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* System Status */}
        <div className="card">
          <h3 className="text-lg font-semibold mb-4">System Status</h3>
          <div className="space-y-3">
            <div className="flex items-center justify-between">
              <span className="text-gray-600">Database Connection</span>
              <span className="flex items-center text-green-600">
                <div className="w-2 h-2 bg-green-500 rounded-full mr-2"></div>
                Online
              </span>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-gray-600">API Services</span>
              <span className="flex items-center text-green-600">
                <div className="w-2 h-2 bg-green-500 rounded-full mr-2"></div>
                Running
              </span>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-gray-600">Data Sync</span>
              <span className="flex items-center text-yellow-600">
                <div className="w-2 h-2 bg-yellow-500 rounded-full mr-2"></div>
                Syncing
              </span>
            </div>
          </div>
        </div>

        {/* Recent Activity */}
        <div className="card">
          <h3 className="text-lg font-semibold mb-4">Recent Activity</h3>
          <div className="space-y-3">
            <div className="flex items-center text-sm">
              <div className="w-8 h-8 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs font-medium mr-3">
                U
              </div>
              <div>
                <p className="text-gray-900">New user registered</p>
                <p className="text-gray-500">2 minutes ago</p>
              </div>
            </div>
            <div className="flex items-center text-sm">
              <div className="w-8 h-8 bg-green-100 text-green-600 rounded-full flex items-center justify-center text-xs font-medium mr-3">
                D
              </div>
              <div>
                <p className="text-gray-900">Data flow completed</p>
                <p className="text-gray-500">5 minutes ago</p>
              </div>
            </div>
            <div className="flex items-center text-sm">
              <div className="w-8 h-8 bg-purple-100 text-purple-600 rounded-full flex items-center justify-center text-xs font-medium mr-3">
                R
              </div>
              <div>
                <p className="text-gray-900">Weekly report generated</p>
                <p className="text-gray-500">1 hour ago</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}