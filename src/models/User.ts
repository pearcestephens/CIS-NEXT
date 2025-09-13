import { User, UserRole } from '@/types'

// Mock database for demonstration - in production this would connect to a real database
let users: User[] = [
  {
    id: '1',
    name: 'John Admin',
    email: 'john@company.com',
    role: UserRole.ADMIN,
    department: 'IT',
    createdAt: new Date('2024-01-01'),
    updatedAt: new Date(),
    isActive: true,
  },
  {
    id: '2',
    name: 'Jane Manager',
    email: 'jane@company.com',
    role: UserRole.MANAGER,
    department: 'Operations',
    createdAt: new Date('2024-01-15'),
    updatedAt: new Date(),
    isActive: true,
  },
  {
    id: '3',
    name: 'Bob Employee',
    email: 'bob@company.com',
    role: UserRole.EMPLOYEE,
    department: 'Sales',
    createdAt: new Date('2024-02-01'),
    updatedAt: new Date(),
    isActive: true,
  }
]

export class UserModel {
  static async findAll(): Promise<User[]> {
    return users.filter(user => user.isActive)
  }

  static async findById(id: string): Promise<User | null> {
    return users.find(user => user.id === id && user.isActive) || null
  }

  static async create(userData: Omit<User, 'id' | 'createdAt' | 'updatedAt'>): Promise<User> {
    const newUser: User = {
      ...userData,
      id: (users.length + 1).toString(),
      createdAt: new Date(),
      updatedAt: new Date(),
    }
    users.push(newUser)
    return newUser
  }

  static async update(id: string, updates: Partial<User>): Promise<User | null> {
    const userIndex = users.findIndex(user => user.id === id)
    if (userIndex === -1) return null

    users[userIndex] = {
      ...users[userIndex],
      ...updates,
      updatedAt: new Date(),
    }
    return users[userIndex]
  }

  static async delete(id: string): Promise<boolean> {
    const userIndex = users.findIndex(user => user.id === id)
    if (userIndex === -1) return false

    users[userIndex].isActive = false
    users[userIndex].updatedAt = new Date()
    return true
  }

  static async getByRole(role: UserRole): Promise<User[]> {
    return users.filter(user => user.role === role && user.isActive)
  }

  static async getByDepartment(department: string): Promise<User[]> {
    return users.filter(user => user.department === department && user.isActive)
  }
}