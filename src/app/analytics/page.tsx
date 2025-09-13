export default function AnalyticsPage() {
  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-3xl font-bold text-gray-900">Analytics & Reports</h1>
      </div>

      {/* Key Performance Indicators */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div className="card">
          <h3 className="text-lg font-semibold mb-3">Data Processing</h3>
          <div className="space-y-2">
            <div className="flex justify-between">
              <span className="text-gray-600">Today</span>
              <span className="font-semibold">2,690 records</span>
            </div>
            <div className="flex justify-between">
              <span className="text-gray-600">This Week</span>
              <span className="font-semibold">18,240 records</span>
            </div>
            <div className="flex justify-between">
              <span className="text-gray-600">This Month</span>
              <span className="font-semibold">75,890 records</span>
            </div>
          </div>
        </div>

        <div className="card">
          <h3 className="text-lg font-semibold mb-3">System Performance</h3>
          <div className="space-y-2">
            <div className="flex justify-between">
              <span className="text-gray-600">Uptime</span>
              <span className="font-semibold text-green-600">99.9%</span>
            </div>
            <div className="flex justify-between">
              <span className="text-gray-600">Response Time</span>
              <span className="font-semibold">120ms</span>
            </div>
            <div className="flex justify-between">
              <span className="text-gray-600">Success Rate</span>
              <span className="font-semibold text-green-600">96.5%</span>
            </div>
          </div>
        </div>

        <div className="card">
          <h3 className="text-lg font-semibold mb-3">User Activity</h3>
          <div className="space-y-2">
            <div className="flex justify-between">
              <span className="text-gray-600">Active Users</span>
              <span className="font-semibold">12 online</span>
            </div>
            <div className="flex justify-between">
              <span className="text-gray-600">Sessions Today</span>
              <span className="font-semibold">45 sessions</span>
            </div>
            <div className="flex justify-between">
              <span className="text-gray-600">Avg. Session</span>
              <span className="font-semibold">24 minutes</span>
            </div>
          </div>
        </div>
      </div>

      {/* Data Flow Analysis */}
      <div className="card">
        <h2 className="text-xl font-semibold mb-4">Data Flow Analysis</h2>
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div>
            <h3 className="text-lg font-medium mb-3">Top Data Sources</h3>
            <div className="space-y-3">
              <div className="flex items-center justify-between">
                <span className="text-gray-700">CRM System</span>
                <div className="flex items-center">
                  <div className="w-32 bg-gray-200 rounded-full h-2 mr-3">
                    <div className="bg-blue-600 h-2 rounded-full" style={{width: '85%'}}></div>
                  </div>
                  <span className="text-sm font-medium">85%</span>
                </div>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-700">Inventory System</span>
                <div className="flex items-center">
                  <div className="w-32 bg-gray-200 rounded-full h-2 mr-3">
                    <div className="bg-green-600 h-2 rounded-full" style={{width: '72%'}}></div>
                  </div>
                  <span className="text-sm font-medium">72%</span>
                </div>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-700">HR System</span>
                <div className="flex items-center">
                  <div className="w-32 bg-gray-200 rounded-full h-2 mr-3">
                    <div className="bg-yellow-600 h-2 rounded-full" style={{width: '58%'}}></div>
                  </div>
                  <span className="text-sm font-medium">58%</span>
                </div>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-gray-700">External APIs</span>
                <div className="flex items-center">
                  <div className="w-32 bg-gray-200 rounded-full h-2 mr-3">
                    <div className="bg-purple-600 h-2 rounded-full" style={{width: '41%'}}></div>
                  </div>
                  <span className="text-sm font-medium">41%</span>
                </div>
              </div>
            </div>
          </div>

          <div>
            <h3 className="text-lg font-medium mb-3">Integration Status</h3>
            <div className="space-y-3">
              <div className="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                <div className="flex items-center">
                  <div className="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                  <span className="text-gray-700">Database Connections</span>
                </div>
                <span className="text-green-700 font-medium">4/4 Active</span>
              </div>
              <div className="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                <div className="flex items-center">
                  <div className="w-3 h-3 bg-blue-500 rounded-full mr-3"></div>
                  <span className="text-gray-700">API Endpoints</span>
                </div>
                <span className="text-blue-700 font-medium">8/10 Online</span>
              </div>
              <div className="flex items-center justify-between p-3 bg-yellow-50 rounded-lg">
                <div className="flex items-center">
                  <div className="w-3 h-3 bg-yellow-500 rounded-full mr-3"></div>
                  <span className="text-gray-700">File Processors</span>
                </div>
                <span className="text-yellow-700 font-medium">2/3 Running</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Reports Section */}
      <div className="card">
        <h2 className="text-xl font-semibold mb-4">Available Reports</h2>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div className="border rounded-lg p-4 hover:bg-gray-50 cursor-pointer transition-colors">
            <h3 className="font-medium text-gray-900 mb-2">Daily Activity Report</h3>
            <p className="text-sm text-gray-600 mb-3">Summary of daily data processing and user activities.</p>
            <span className="text-primary-600 text-sm font-medium">Generate Report →</span>
          </div>
          
          <div className="border rounded-lg p-4 hover:bg-gray-50 cursor-pointer transition-colors">
            <h3 className="font-medium text-gray-900 mb-2">System Performance Report</h3>
            <p className="text-sm text-gray-600 mb-3">Detailed analysis of system performance metrics.</p>
            <span className="text-primary-600 text-sm font-medium">Generate Report →</span>
          </div>
          
          <div className="border rounded-lg p-4 hover:bg-gray-50 cursor-pointer transition-colors">
            <h3 className="font-medium text-gray-900 mb-2">Integration Health Report</h3>
            <p className="text-sm text-gray-600 mb-3">Status and health check of all system integrations.</p>
            <span className="text-primary-600 text-sm font-medium">Generate Report →</span>
          </div>
        </div>
      </div>
    </div>
  )
}