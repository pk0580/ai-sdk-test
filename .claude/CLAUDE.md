# Claude Configuration

## Active Skills

- laravel-ddd-architect

## Default Mode

- Use full DDD architecture by default
- Use simplified mode only for trivial CRUD

## Code Generation Rules

- Always generate full structure:
  - Domain
  - Application
  - Infrastructure
  - Interface

- Always include:
  - Entity
  - Value Objects
  - Repository
  - UseCase
  - DTO
  - Controller
  - FormRequest

## Architecture Constraints

- No Eloquent in Domain
- No business logic in Controllers
- Strict dependency direction

## Scaling Strategy

- Use queues for async operations
- Use caching where needed
- Suggest read models for heavy queries