import { NextRequest, NextResponse } from 'next/server'
import { UserController } from '@/controllers/UserController'
import { UserRole } from '@/types'

export async function GET(request: NextRequest) {
  try {
    const { searchParams } = new URL(request.url)
    const role = searchParams.get('role') as UserRole
    const department = searchParams.get('department')

    let users
    if (role) {
      users = await UserController.getUsersByRole(role)
    } else if (department) {
      users = await UserController.getUsersByDepartment(department)
    } else {
      users = await UserController.getAllUsers()
    }

    return NextResponse.json({ success: true, data: users })
  } catch (error) {
    console.error('Error in GET /api/users:', error)
    return NextResponse.json(
      { success: false, error: 'Failed to fetch users' },
      { status: 500 }
    )
  }
}

export async function POST(request: NextRequest) {
  try {
    const body = await request.json()
    const { name, email, role, department } = body

    const user = await UserController.createUser({
      name,
      email,
      role,
      department,
    })

    return NextResponse.json({ success: true, data: user }, { status: 201 })
  } catch (error) {
    console.error('Error in POST /api/users:', error)
    return NextResponse.json(
      { success: false, error: error instanceof Error ? error.message : 'Failed to create user' },
      { status: 400 }
    )
  }
}