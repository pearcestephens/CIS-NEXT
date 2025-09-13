import Link from 'next/link'
import { DashboardStats } from '@/components/DashboardStats'
import { DataFlowVisualization } from '@/components/DataFlowVisualization'

export default function HomePage() {
  return (
    <div className="space-y-8">
      {/* Header */}
      <div className="bg-white rounded-lg shadow-md p-6">
        <h1 className="text-3xl font-bold text-gray-900 mb-2">
          Welcome to CIS-NEXT ERP Platform
        </h1>
        <p className="text-gray-600 mb-4">
          Manage data flow between people and technology systems with our comprehensive MVC ERP solution.
        </p>
        <div className="flex space-x-4">
          <Link href="/dashboard" className="btn-primary">
            Go to Dashboard
          </Link>
          <Link href="/users" className="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg transition-colors">
            Manage Users
          </Link>
        </div>
      </div>

      {/* Dashboard Stats */}
      <DashboardStats />

      {/* Data Flow Visualization */}
      <DataFlowVisualization />
      
      {/* Quick Actions */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div className="card">
          <h3 className="text-lg font-semibold mb-2">User Management</h3>
          <p className="text-gray-600 mb-4">Manage users, roles, and permissions across the platform.</p>
          <Link href="/users" className="text-primary-600 hover:text-primary-700 font-medium">
            Manage Users →
          </Link>
        </div>
        
        <div className="card">
          <h3 className="text-lg font-semibold mb-2">Data Analytics</h3>
          <p className="text-gray-600 mb-4">View reports and analytics on data flow and system usage.</p>
          <Link href="/analytics" className="text-primary-600 hover:text-primary-700 font-medium">
            View Analytics →
          </Link>
        </div>
        
        <div className="card">
          <h3 className="text-lg font-semibold mb-2">System Integration</h3>
          <p className="text-gray-600 mb-4">Configure integrations with external systems and APIs.</p>
          <Link href="/integrations" className="text-primary-600 hover:text-primary-700 font-medium">
            Manage Integrations →
          </Link>
        </div>
      </div>
    </div>
  )
}