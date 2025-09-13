import { NextRequest, NextResponse } from 'next/server'
import { DataFlowModel } from '@/models/DataFlow'
import { DataFlowStatus } from '@/types'

export async function GET(request: NextRequest) {
  try {
    const { searchParams } = new URL(request.url)
    const status = searchParams.get('status') as DataFlowStatus

    let dataFlows
    if (status) {
      dataFlows = await DataFlowModel.findByStatus(status)
    } else {
      dataFlows = await DataFlowModel.findAll()
    }

    return NextResponse.json({ success: true, data: dataFlows })
  } catch (error) {
    console.error('Error in GET /api/data-flows:', error)
    return NextResponse.json(
      { success: false, error: 'Failed to fetch data flows' },
      { status: 500 }
    )
  }
}

export async function POST(request: NextRequest) {
  try {
    const body = await request.json()
    const { source, destination, dataType, volume } = body

    const dataFlow = await DataFlowModel.create({
      source,
      destination,
      dataType,
      volume: volume || 0,
      timestamp: new Date(),
      status: DataFlowStatus.PENDING,
    })

    return NextResponse.json({ success: true, data: dataFlow }, { status: 201 })
  } catch (error) {
    console.error('Error in POST /api/data-flows:', error)
    return NextResponse.json(
      { success: false, error: 'Failed to create data flow' },
      { status: 400 }
    )
  }
}