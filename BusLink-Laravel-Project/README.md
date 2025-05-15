# BusLink - Transport Tech Solution

## Project Overview

BusLink is an innovative transport tech solution aimed at revolutionizing Rwanda's public transport system. It addresses issues such as long waiting times at bus stops, efficient driver management, and paramount road safety, in addition to guiding bus drivers which roads to choose based on traffic congestion.

By leveraging cutting-edge technology and data-driven insights, the project streamlines the transportation ecosystem, reducing waiting times through smart scheduling algorithms. It also enhances driver performance through real-time monitoring, training, and performance evaluation.

## Features Implemented

### User Management
- Authentication (login/register) for different user roles (admin, driver, passenger)
- User profile management
- Role-based access control

### Bus/Car Management
- Create, read, update, delete bus details
- Track bus status (active, maintenance, blocked)
- Manage bus features and capacity
- Block/unblock buses
- Assign buses to drivers

### Routes & Stations Management
- Define routes with multiple stops/stations
- Manage station information (coordinates, arrival/departure times)
- Add/remove/reorder stations in routes
- Assign drivers to routes

### Schedule Management
- Create bus schedules with specific days and times
- Check for conflicting schedules
- Manage schedule status (active, cancelled, completed)
- View schedules specific to drivers

### Booking Management
- Book seats on specific routes
- View booking history
- Cancel bookings
- Drivers can view passenger lists

### Traffic and Navigation
- Geocode locations (convert addresses to coordinates)
- Get traffic information between two points
- Find the best route based on traffic conditions
- Track buses in real-time
- Calculate estimated arrival times
- Find nearby bus stations

### Driver Management
- Monitor driver performance
- Track driver arrival times
- View driver efficiency metrics
- Block/unblock drivers

### Reports & Analytics
- Dashboard statistics
- Passenger activity reports
- Driver performance reports
- Route analysis
- Car utilization reports
- Custom report generation

### Notification System
- Send notifications to users about schedule changes, bookings, etc.
- Track read/unread status
- Filter notifications by type
- Mark notifications as read/unread

## API Endpoints

### Authentication
- POST `/api/register` - Register a new user
- POST `/api/login` - Login and get authentication token
- GET `/api/user` - Get authenticated user details
- POST `/api/logout` - Logout user

### Admin Routes
- GET `/api/drivers` - Get all drivers
- PATCH `/api/drivers/{driver}/toggle-block` - Block/unblock a driver
- GET `/api/passengers` - Get all passengers
- GET `/api/feedback` - Get all feedback
- GET `/api/feedback/reported-drivers` - Get drivers with feedback reports

### Bus Management (Admin)
- GET `/api/cars` - Get all buses/cars
- POST `/api/cars` - Add a new bus/car
- GET `/api/cars/{car}` - View specific bus/car details
- PUT `/api/cars/{car}` - Update bus/car details
- DELETE `/api/cars/{car}` - Remove a bus/car
- PATCH `/api/cars/{car}/status` - Change bus/car status

### Route Management (Admin)
- GET `/api/routes` - Get all routes
- POST `/api/routes` - Create a new route
- GET `/api/routes/{route}` - View specific route details
- PUT `/api/routes/{route}` - Update route details
- DELETE `/api/routes/{route}` - Remove a route
- POST `/api/routes/{route}/assign-driver/{driver}` - Assign driver to route

### Station Management (Admin)
- GET `/api/stations` - Get all stations
- POST `/api/stations` - Create a new station
- GET `/api/stations/{station}` - View specific station details
- PUT `/api/stations/{station}` - Update station details
- DELETE `/api/stations/{station}` - Remove a station
- POST `/api/routes/{route}/stations` - Add station to route
- PUT `/api/routes/{route}/stations/{station}` - Update station in route
- DELETE `/api/routes/{route}/stations/{station}` - Remove station from route

### Schedule Management (Admin)
- GET `/api/schedules` - Get all schedules
- POST `/api/schedules` - Create a new schedule
- GET `/api/schedules/{schedule}` - View specific schedule details
- PUT `/api/schedules/{schedule}` - Update schedule details
- DELETE `/api/schedules/{schedule}` - Remove a schedule
- PATCH `/api/schedules/{schedule}/status` - Change schedule status

### Reports & Analytics (Admin)
- GET `/api/reports/dashboard` - Get dashboard statistics
- GET `/api/reports/passengers` - Get passenger activity reports
- GET `/api/reports/drivers` - Get driver performance reports
- GET `/api/reports/routes` - Get route analysis reports
- GET `/api/reports/cars` - Get car utilization reports
- GET `/api/reports/custom` - Generate custom reports

### Driver Routes
- GET `/api/profile` - Get driver profile
- GET `/api/performance` - Get driver performance metrics
- GET `/api/bookings` - Get bookings for driver's route
- POST `/api/location` - Update driver location
- POST `/api/arrival` - Check in driver arrival time
- GET `/api/my-route` - Get driver's assigned route details
- GET `/api/my-schedule` - Get driver's schedule
- GET `/api/my-car` - Get driver's assigned car details

### Passenger Routes
- GET `/api/bookings` - Get user's bookings
- POST `/api/bookings` - Book a seat
- GET `/api/bookings/{booking}` - View booking details
- DELETE `/api/bookings/{booking}` - Cancel booking
- POST `/api/feedback` - Submit feedback
- GET `/api/track/{booking}` - Track booked bus

### Notification Routes (All Users)
- GET `/api/notifications` - Get user notifications
- GET `/api/notifications/count` - Get unread notification count
- PATCH `/api/notifications/{notification}/read` - Mark notification as read
- PATCH `/api/notifications/read-all` - Mark all notifications as read
- DELETE `/api/notifications/{notification}` - Delete notification
- DELETE `/api/notifications` - Delete all notifications

## Installation and Setup

1. Clone the repository
   ```
   git clone <repository-url>
   ```

2. Install composer dependencies
   ```
   composer install
   ```

3. Copy the `.env.example` file to `.env` and configure database settings
   ```
   cp .env.example .env
   ```

4. Generate application key
   ```
   php artisan key:generate
   ```

5. Run database migrations
   ```
   php artisan migrate
   ```

6. Add Google Maps API key to .env file (required for geocoding, navigation, and tracking)
   ```
   GOOGLE_MAPS_API_KEY=your_google_maps_api_key_here
   ```
   
   You'll need to enable the following Google Maps APIs in your Google Cloud Console:
   - Geocoding API
   - Directions API
   - Places API
   - Distance Matrix API

7. Start the development server
   ```
   php artisan serve
   ```

## License

[MIT License](LICENSE)
