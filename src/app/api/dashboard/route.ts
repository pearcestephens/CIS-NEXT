import { NextResponse } from 'next/server'
import { DashboardController } from '@/controllers/DashboardController'

export async function GET() {
  try {
    const metrics = await DashboardController.getDashboardMetrics()
    return NextResponse.json({ success: true, data: metrics })
  } catch (error) {
    console.error('Error in GET /api/dashboard:', error)
    return NextResponse.json(
      { success: false, error: 'Failed to fetch dashboard metrics' },
      { status: 500 }
    )
  }
}