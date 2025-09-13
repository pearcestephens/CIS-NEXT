import type { Metadata } from 'next'
import './globals.css'

export const metadata: Metadata = {
  title: 'CIS-NEXT ERP Platform',
  description: 'MVC ERP Platform for data flow management between people and technology',
}

export default function RootLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <html lang="en">
      <body className="font-sans">
        <div className="min-h-screen bg-gray-50">
          <nav className="bg-white shadow-sm border-b">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
              <div className="flex justify-between h-16">
                <div className="flex items-center">
                  <h1 className="text-2xl font-bold text-primary-600">CIS-NEXT</h1>
                  <span className="ml-2 text-sm text-gray-500">ERP Platform</span>
                </div>
              </div>
            </div>
          </nav>
          <main className="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            {children}
          </main>
        </div>
      </body>
    </html>
  )
}