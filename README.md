# CIS-NEXT ERP Platform

A comprehensive MVC ERP (Enterprise Resource Planning) platform built with Next.js for managing data flow between people and technology systems.

## Features

### Core ERP Functionality
- **User Management**: Complete user lifecycle management with roles and permissions
- **Data Flow Monitoring**: Real-time tracking of data flows between systems
- **Analytics & Reporting**: Comprehensive dashboards and reporting capabilities
- **System Integration**: Connect with external systems and APIs

### MVC Architecture
- **Models**: Data models and business logic for Users, DataFlows, and System Integrations
- **Views**: React components and pages for user interfaces
- **Controllers**: Business logic handlers and API route controllers

### Technology Stack
- **Frontend**: Next.js 15, React 19, TypeScript
- **Styling**: Tailwind CSS with custom components
- **Architecture**: Model-View-Controller (MVC) pattern
- **Data**: Mock data layer (ready for database integration)

## Getting Started

### Prerequisites
- Node.js 18+ 
- npm or yarn

### Installation

1. Clone the repository
```bash
git clone https://github.com/pearcestephens/CIS-NEXT.git
cd CIS-NEXT
```

2. Install dependencies
```bash
npm install
```

3. Start the development server
```bash
npm run dev
```

4. Open [http://localhost:3000](http://localhost:3000) in your browser

### Build for Production
```bash
npm run build
npm start
```

## Project Structure

```
src/
├── app/                    # Next.js App Router pages
│   ├── api/               # API routes (Controllers)
│   ├── dashboard/         # Dashboard page
│   ├── users/             # User management page
│   ├── analytics/         # Analytics and reports page
│   └── globals.css        # Global styles
├── components/            # React components (Views)
├── controllers/           # Business logic controllers
├── models/               # Data models and business logic
├── types/                # TypeScript type definitions
└── lib/                  # Utility functions
```

## API Endpoints

- `GET /api/users` - List all users
- `POST /api/users` - Create new user
- `GET /api/dashboard` - Get dashboard metrics
- `GET /api/data-flows` - Get data flow information

## Key Features

### Dashboard
- Real-time system metrics
- Data flow monitoring
- System health indicators
- Recent activity tracking

### User Management
- Create, read, update users
- Role-based access control
- Department management
- User activity tracking

### Analytics
- Data processing metrics
- System performance monitoring
- Integration status tracking
- Custom report generation

### Data Flow Management
- Monitor data flows between systems
- Track volume and success rates
- Real-time status updates
- Integration health checks

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the ISC License - see the package.json file for details.

## About

CIS-NEXT is designed to help local companies manage the flow of data in and out of their systems, facilitating communication between people and various pieces of technology infrastructure.
