import { UserModel } from '@/models/User'
import { User, UserRole } from '@/types'

export class UserController {
  static async getAllUsers(): Promise<User[]> {
    try {
      return await UserModel.findAll()
    } catch (error) {
      console.error('Error fetching users:', error)
      throw new Error('Failed to fetch users')
    }
  }

  static async getUserById(id: string): Promise<User | null> {
    try {
      return await UserModel.findById(id)
    } catch (error) {
      console.error('Error fetching user:', error)
      throw new Error('Failed to fetch user')
    }
  }

  static async createUser(userData: {
    name: string
    email: string
    role: UserRole
    department?: string
  }): Promise<User> {
    try {
      // Validate required fields
      if (!userData.name || !userData.email || !userData.role) {
        throw new Error('Name, email, and role are required')
      }

      // Validate email format
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
      if (!emailRegex.test(userData.email)) {
        throw new Error('Invalid email format')
      }

      const newUser = await UserModel.create({
        ...userData,
        isActive: true,
      })

      return newUser
    } catch (error) {
      console.error('Error creating user:', error)
      throw error
    }
  }

  static async updateUser(id: string, updates: Partial<User>): Promise<User | null> {
    try {
      // Remove fields that shouldn't be updated directly
      const { id: _, createdAt, ...allowedUpdates } = updates
      
      return await UserModel.update(id, {
        ...allowedUpdates,
        updatedAt: new Date(),
      })
    } catch (error) {
      console.error('Error updating user:', error)
      throw new Error('Failed to update user')
    }
  }

  static async deleteUser(id: string): Promise<boolean> {
    try {
      return await UserModel.delete(id)
    } catch (error) {
      console.error('Error deleting user:', error)
      throw new Error('Failed to delete user')
    }
  }

  static async getUsersByRole(role: UserRole): Promise<User[]> {
    try {
      return await UserModel.getByRole(role)
    } catch (error) {
      console.error('Error fetching users by role:', error)
      throw new Error('Failed to fetch users by role')
    }
  }

  static async getUsersByDepartment(department: string): Promise<User[]> {
    try {
      return await UserModel.getByDepartment(department)
    } catch (error) {
      console.error('Error fetching users by department:', error)
      throw new Error('Failed to fetch users by department')
    }
  }
}