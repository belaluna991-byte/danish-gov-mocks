# Contributing to Danish Government Mock Services

Thank you for your interest in contributing to this project! This repository aims to make testing Danish government service integrations easier for all developers.

## How to Contribute

### Reporting Issues

If you encounter a problem or have a suggestion:

1. Check existing [issues](https://github.com/YOUR_USERNAME/danish-gov-mocks/issues) to avoid duplicates
2. Create a new issue with:
   - Clear, descriptive title
   - Steps to reproduce (for bugs)
   - Expected vs actual behavior
   - Your environment (OS, Docker version)

### Suggesting Enhancements

We welcome suggestions for:

- Additional test personas
- New Danish government services to mock
- Documentation improvements
- Integration examples for other frameworks

Open an issue with the `enhancement` label to start the discussion.

### Pull Requests

1. **Fork the repository**
   ```bash
   git clone https://github.com/YOUR_USERNAME/danish-gov-mocks.git
   cd danish-gov-mocks
   ```

2. **Create a feature branch**
   ```bash
   git checkout -b feature/your-feature-name
   ```

3. **Make your changes**
   - Follow existing code style and patterns
   - Update documentation if needed
   - Test your changes locally

4. **Commit with clear messages**
   ```bash
   git commit -m "Add SF1234 service mock for [service name]"
   ```

5. **Push and create pull request**
   ```bash
   git push origin feature/your-feature-name
   ```

## Contribution Guidelines

### Adding Test Personas

When adding test users to Keycloak realm:

- Use realistic but fake Danish names
- Generate valid CPR numbers (format: DDMMYYXXXX)
- Include diverse scenarios (age groups, business vs personal)
- Document in `docs/test-data.md`

### Adding WireMock Stubs

When adding Serviceplatformen mocks:

- Place mapping in correct service directory (`sf1520/`, `sf1530/`, etc.)
- Use descriptive filenames: `sf1520-cpr-{number}.json`
- Place response XML in corresponding `__files/` directory
- Follow OIO XML standards
- Test with actual SOAP client

### Documentation Standards

- Write in clear, concise English
- Include Danish terminology where appropriate
- Add code examples that can be copy-pasted
- Update README.md if adding major features

### Code Style

- **YAML files**: 2-space indentation
- **JSON files**: 2-space indentation, no trailing commas
- **Markdown**: Use descriptive headers, code blocks with language tags
- **XML**: Follow OIO formatting standards

## Testing Your Changes

Before submitting a pull request:

```bash
# Start all services
docker compose up -d

# Verify Keycloak
curl http://localhost:8080/health/ready

# Verify WireMock
curl http://localhost:8081/__admin/health

# Test OIDC flow with new persona (if applicable)
# Test SOAP request with new stub (if applicable)

# Stop services
docker compose down
```

## Code of Conduct

### Our Standards

- Be respectful and inclusive
- Welcome newcomers and help them learn
- Focus on constructive feedback
- Assume good intentions

### Unacceptable Behavior

- Harassment or discriminatory language
- Trolling or insulting comments
- Publishing others' private information
- Any conduct inappropriate in a professional setting

## Questions?

Open a [discussion](https://github.com/YOUR_USERNAME/danish-gov-mocks/discussions) or reach out via issues.

## License

By contributing, you agree that your contributions will be licensed under the Apache License 2.0.
