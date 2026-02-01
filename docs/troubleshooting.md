# Troubleshooting Guide

Common issues and solutions for the Danish Government Mock Services.

## Service Startup Issues

### Keycloak Takes Too Long to Start

**Symptoms**: Keycloak container stays in "starting" state for several minutes.

**Cause**: Realm import on first startup can be slow.

**Solution**:
```bash
# Check startup logs
docker compose logs -f keycloak

# Wait for this message:
# "Keycloak 23.0 started in XXms"

# Or check health endpoint
curl http://localhost:8080/health/ready
```

**Timeout**: First start can take 60-90 seconds. Subsequent starts are faster (10-20 seconds).

---

### Port Already in Use

**Symptoms**: Error message: `Bind for 0.0.0.0:8080 failed: port is already allocated`

**Cause**: Another service is using ports 8080, 8081, or 8082.

**Solution 1**: Change ports in `.env`
```bash
# Edit .env file
KEYCLOAK_PORT=9080
WIREMOCK_PORT=9081
PRISM_PORT=9082

# Restart services
docker compose down
docker compose up -d
```

**Solution 2**: Find and stop conflicting service
```bash
# Linux/Mac - Find process using port 8080
lsof -i :8080

# Kill the process
kill -9 <PID>

# Windows - Find process
netstat -ano | findstr :8080

# Kill the process
taskkill /PID <PID> /F
```

---

### Docker Compose Command Not Found

**Symptoms**: `docker compose: command not found`

**Cause**: Using old Docker version or Docker Compose v1.

**Solution**:
```bash
# Try with hyphen (Docker Compose v1)
docker-compose up -d

# Or update Docker to latest version
# Docker Desktop includes Compose v2
```

---

### Container Exits Immediately

**Symptoms**: Container starts then immediately stops.

**Check logs**:
```bash
# View logs for specific service
docker compose logs keycloak
docker compose logs wiremock

# Check container status
docker compose ps
```

**Common causes**:
- **Invalid volume mount**: Check that directories exist
- **Permission denied**: Ensure Docker has access to project directory
- **Corrupted image**: Pull image again

```bash
# Remove and re-pull images
docker compose down
docker compose pull
docker compose up -d
```

---

## Connection Issues

### Cannot Access Admin Console

**Symptoms**: `http://localhost:8080/admin` returns "Connection refused"

**Checklist**:
1. Verify container is running:
   ```bash
   docker compose ps
   ```

2. Check port mapping:
   ```bash
   docker compose port keycloak 8080
   ```

3. Check health status:
   ```bash
   curl http://localhost:8080/health/ready
   ```

4. View logs for errors:
   ```bash
   docker compose logs keycloak
   ```

---

### OIDC Discovery Returns 404

**Symptoms**: `http://localhost:8080/realms/danish-gov-test/.well-known/openid-configuration` not found

**Cause**: Realm not imported yet or import failed.

**Solution**:
```bash
# Check Keycloak logs for import errors
docker compose logs keycloak | grep -i import

# Verify realm file exists
ls -la keycloak/realms/danish-gov-test.json

# Restart with fresh import
docker compose down
docker compose up -d keycloak
```

---

### WireMock Returns 404 for SOAP Requests

**Symptoms**: SOAP requests return 404 instead of mocked response

**Debugging steps**:

1. **Check stub mappings are loaded**:
   ```bash
   curl http://localhost:8081/__admin/mappings
   ```

2. **Verify mapping files exist**:
   ```bash
   ls -la wiremock/mappings/sf1520/
   ```

3. **Check logs for matching errors**:
   ```bash
   docker compose logs wiremock
   ```

4. **Test with simple request**:
   ```bash
   curl -X POST http://localhost:8081/soap/sf1520 \
     -H "Content-Type: text/xml" \
     -d '<test>request</test>'
   ```

**Common causes**:
- **Wrong URL path**: Verify endpoint path in mapping file
- **Body pattern mismatch**: Check XPath or text pattern in mapping
- **File permissions**: Ensure mapping files are readable

---

## Authentication Issues

### Cannot Login with Test User

**Symptoms**: "Invalid username or password" when using `freja.nielsen` / `test1234`

**Checklist**:

1. **Verify realm is danish-gov-test**:
   Check URL: `http://localhost:8080/realms/danish-gov-test/...`

2. **Confirm user exists**:
   - Login to admin console: `http://localhost:8080/admin`
   - Username: `admin`, Password: `admin`
   - Navigate to: Users → View all users
   - Search for: `freja.nielsen`

3. **Check user is enabled**:
   - User details → Ensure "Enabled" is ON
   - Credentials tab → Verify password is set

4. **Try admin credentials**:
   If admin login works (`admin`/`admin`), realm is working but user may have issue.

---

### OIDC Token Missing CPR Claim

**Symptoms**: ID token doesn't contain `cpr` field

**Cause**: Protocol mapper not configured.

**Solution**:

1. **Check client protocol mappers**:
   - Admin console → Clients → `aabenforms-backend`
   - Client scopes tab → Evaluate
   - Select user: `freja.nielsen`
   - Check "Generated ID Token" contains `cpr`

2. **Verify protocol mapper exists**:
   - Clients → `aabenforms-backend` → Client scopes
   - Should have mapper named "cpr"

3. **Re-import realm**:
   ```bash
   docker compose down
   docker compose up -d
   ```

---

## CI/CD Issues

### Services Don't Start in GitHub Actions

**Symptoms**: Health checks fail in CI pipeline

**Common causes**:

1. **Insufficient startup time**: Add longer health check intervals
   ```yaml
   healthcheck:
     start_period: 90s  # Increase for slower CI runners
   ```

2. **Port conflicts**: GitHub Actions may have services on default ports
   ```yaml
   ports:
     - 8080:8080  # Try random port: 0:8080
   ```

3. **Volume mounts**: Service containers can't mount local files
   ```yaml
   # Don't use relative paths in GitHub Actions services
   # Copy files in workflow steps instead
   ```

**Recommended approach**: Use docker compose in workflow steps, not services:
```yaml
steps:
  - uses: actions/checkout@v3
  - name: Start mocks
    run: docker compose up -d
  - name: Wait for ready
    run: |
      timeout 60 bash -c 'until curl -f http://localhost:8080/health/ready; do sleep 2; done'
```

---

### GitLab CI Service Connection Refused

**Symptoms**: Application can't connect to `http://keycloak:8080`

**Cause**: Service network configuration in GitLab CI.

**Solution**: Use service alias correctly
```yaml
services:
  - name: quay.io/keycloak/keycloak:23.0
    alias: keycloak  # Service accessible at http://keycloak:8080
    environment:
      KEYCLOAK_ADMIN: admin
      KEYCLOAK_ADMIN_PASSWORD: admin
```

---

## Data Issues

### Realm Import Shows No Users

**Symptoms**: Keycloak starts but realm has no test users

**Cause**: Import failed or wrong realm file.

**Debug**:
```bash
# Check import logs
docker compose logs keycloak | grep -A 20 "import"

# Verify JSON is valid
cat keycloak/realms/danish-gov-test.json | jq .

# Check file is mounted
docker compose exec keycloak ls /opt/keycloak/data/import/
```

**Solution**:
```bash
# Validate JSON
# Fix any syntax errors

# Force fresh import
docker compose down -v  # Remove volumes
docker compose up -d
```

---

### SOAP Response Has Wrong Data

**Symptoms**: WireMock returns response but data is incorrect

**Cause**: Wrong response file matched or templating issue.

**Debug**:
1. Check which mapping matched:
   ```bash
   docker compose logs wiremock | grep "Matched"
   ```

2. View exact request/response:
   ```bash
   curl http://localhost:8081/__admin/requests
   ```

3. Verify response file content:
   ```bash
   cat wiremock/__files/sf1520/freja-nielsen.xml
   ```

---

## Performance Issues

### Keycloak Very Slow

**Symptoms**: Login takes 10+ seconds, token requests timeout

**Cause**: Development mode overhead.

**Solutions**:

1. **Allocate more resources**:
   ```yaml
   # docker-compose.yml
   keycloak:
     deploy:
       resources:
         limits:
           memory: 1G
         reservations:
           memory: 512M
   ```

2. **Disable unused features**: Edit realm configuration to disable:
   - Brute force detection (already off in test realm)
   - Login events (if not needed)

3. **Use production mode** (not recommended for local testing):
   ```yaml
   command:
     - start  # Instead of start-dev
   ```

---

## Docker Issues

### Permission Denied Errors

**Symptoms**: `Error: permission denied` when starting services

**Linux-specific**: Docker socket permissions

```bash
# Add user to docker group
sudo usermod -aG docker $USER

# Logout and login again, or:
newgrp docker

# Verify
docker ps
```

---

### Disk Space Issues

**Symptoms**: `no space left on device`

**Solution**:
```bash
# Remove unused Docker resources
docker system prune -a

# Remove volumes (CAUTION: deletes data)
docker compose down -v

# Check disk usage
docker system df
```

---

## Network Issues

### Cannot Connect from Host Application

**Symptoms**: Application on host machine can't reach `http://localhost:8080`

**Cause**: Network isolation or firewall.

**Solutions**:

1. **Verify port is published**:
   ```bash
   docker compose ps
   # Should show: 0.0.0.0:8080->8080/tcp
   ```

2. **Test from host**:
   ```bash
   curl http://localhost:8080/health/ready
   ```

3. **Try 127.0.0.1 instead of localhost**:
   ```bash
   curl http://127.0.0.1:8080/health/ready
   ```

4. **Check firewall**:
   ```bash
   # Linux
   sudo ufw status

   # macOS
   sudo pfctl -s all
   ```

---

### Services Can't Communicate (Container to Container)

**Symptoms**: One container can't reach another

**Solution**: Ensure both on same network
```bash
# Check networks
docker network inspect danish-gov-mocks

# Services should reference network name
docker compose exec keycloak ping wiremock
```

---

## Still Having Issues?

### Collect Diagnostic Information

```bash
# System info
docker version
docker compose version

# Service status
docker compose ps

# Recent logs
docker compose logs --tail=100

# Container inspection
docker compose exec keycloak env
```

### Report an Issue

If the problem persists:

1. **Check existing issues**: https://github.com/YOUR_USERNAME/danish-gov-mocks/issues
2. **Create new issue** with:
   - Steps to reproduce
   - Expected vs actual behavior
   - Docker version and OS
   - Relevant log output
   - Configuration files (sanitize secrets)

### Community Help

- **Discussions**: https://github.com/YOUR_USERNAME/danish-gov-mocks/discussions
- **Keycloak Help**: https://github.com/keycloak/keycloak/discussions
- **WireMock Help**: https://slack.wiremock.org/

---

## Quick Reset

If all else fails, complete reset:

```bash
# Stop and remove everything
docker compose down -v

# Remove images
docker compose down --rmi all

# Pull fresh images
docker compose pull

# Start fresh
docker compose up -d

# Wait and verify
sleep 30
curl http://localhost:8080/health/ready
curl http://localhost:8081/__admin/health
```

This should resolve most issues by starting from a clean state.
