#!/bin/bash

# OnlyOffice Docker Manager Script
# Manages the OnlyOffice Document Server container with auto-recovery

CONTAINER_NAME="nexio-documentserver"
IMAGE_NAME="onlyoffice/documentserver:latest"
PORT="8082"
NETWORK_NAME="nexio-network"
JWT_SECRET="mySecureJwtSecret123"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored messages
print_message() {
    local color=$1
    local message=$2
    echo -e "${color}${message}${NC}"
}

# Function to check if container exists
container_exists() {
    docker ps -a --format "table {{.Names}}" | grep -q "^${CONTAINER_NAME}$"
}

# Function to check if container is running
container_running() {
    docker ps --format "table {{.Names}}" | grep -q "^${CONTAINER_NAME}$"
}

# Function to check if OnlyOffice is responding
check_health() {
    curl -sf http://localhost:${PORT}/web-apps/apps/api/documents/api.js > /dev/null 2>&1
    return $?
}

# Function to create network if it doesn't exist
create_network() {
    if ! docker network ls | grep -q "${NETWORK_NAME}"; then
        print_message $YELLOW "Creating Docker network: ${NETWORK_NAME}"
        docker network create ${NETWORK_NAME}
    fi
}

# Function to start the container
start_container() {
    print_message $GREEN "Starting OnlyOffice Document Server..."
    
    # Create network if needed
    create_network
    
    # Check if container exists
    if container_exists; then
        if container_running; then
            print_message $YELLOW "Container is already running. Checking health..."
            if check_health; then
                print_message $GREEN "✓ OnlyOffice is healthy and responding"
                return 0
            else
                print_message $RED "Container is running but not healthy. Restarting..."
                docker stop ${CONTAINER_NAME}
                docker rm ${CONTAINER_NAME}
            fi
        else
            print_message $YELLOW "Container exists but is not running. Removing and recreating..."
            docker rm ${CONTAINER_NAME}
        fi
    fi
    
    # Run the container
    print_message $GREEN "Creating new container..."
    docker run -d \
        --name ${CONTAINER_NAME} \
        --network ${NETWORK_NAME} \
        -p ${PORT}:80 \
        -e JWT_ENABLED=true \
        -e JWT_SECRET=${JWT_SECRET} \
        -e JWT_HEADER=Authorization \
        -e JWT_IN_BODY=true \
        -v /mnt/c/xampp/htdocs/piattaforma-collaborativa/documents/onlyoffice:/var/www/onlyoffice/Data \
        --restart unless-stopped \
        ${IMAGE_NAME}
    
    # Wait for container to be ready
    print_message $YELLOW "Waiting for OnlyOffice to be ready..."
    sleep 10
    
    # Check health
    local attempts=0
    while [ $attempts -lt 30 ]; do
        if check_health; then
            print_message $GREEN "✓ OnlyOffice is ready and responding on http://localhost:${PORT}"
            return 0
        fi
        attempts=$((attempts + 1))
        echo -n "."
        sleep 2
    done
    
    print_message $RED "✗ OnlyOffice failed to start properly"
    return 1
}

# Function to stop the container
stop_container() {
    print_message $YELLOW "Stopping OnlyOffice Document Server..."
    docker stop ${CONTAINER_NAME}
    print_message $GREEN "✓ Container stopped"
}

# Function to restart the container
restart_container() {
    print_message $YELLOW "Restarting OnlyOffice Document Server..."
    stop_container
    sleep 2
    start_container
}

# Function to show container status
show_status() {
    print_message $YELLOW "OnlyOffice Document Server Status:"
    echo "=================================="
    
    if container_exists; then
        if container_running; then
            print_message $GREEN "✓ Container is running"
            
            if check_health; then
                print_message $GREEN "✓ API is responding"
                echo ""
                echo "Access URLs:"
                echo "  - Document Server: http://localhost:${PORT}"
                echo "  - API Endpoint: http://localhost:${PORT}/web-apps/apps/api/documents/api.js"
                echo "  - Welcome Page: http://localhost:${PORT}/welcome/"
                echo ""
                echo "Container details:"
                docker ps --filter "name=${CONTAINER_NAME}" --format "table {{.ID}}\t{{.Status}}\t{{.Ports}}"
            else
                print_message $RED "✗ API is not responding"
                echo ""
                echo "Checking logs..."
                docker logs ${CONTAINER_NAME} --tail 10
            fi
        else
            print_message $RED "✗ Container exists but is not running"
        fi
    else
        print_message $RED "✗ Container does not exist"
    fi
}

# Function to show logs
show_logs() {
    if container_exists; then
        print_message $YELLOW "Showing last 50 lines of logs:"
        docker logs ${CONTAINER_NAME} --tail 50
    else
        print_message $RED "Container does not exist"
    fi
}

# Function to remove container completely
remove_container() {
    print_message $YELLOW "Removing OnlyOffice container..."
    docker stop ${CONTAINER_NAME} 2>/dev/null
    docker rm ${CONTAINER_NAME} 2>/dev/null
    print_message $GREEN "✓ Container removed"
}

# Main script logic
case "$1" in
    start)
        start_container
        ;;
    stop)
        stop_container
        ;;
    restart)
        restart_container
        ;;
    status)
        show_status
        ;;
    logs)
        show_logs
        ;;
    remove)
        remove_container
        ;;
    health)
        if check_health; then
            print_message $GREEN "✓ OnlyOffice is healthy"
            exit 0
        else
            print_message $RED "✗ OnlyOffice is not responding"
            exit 1
        fi
        ;;
    auto-fix)
        print_message $YELLOW "Running auto-fix..."
        if ! check_health; then
            print_message $RED "OnlyOffice is not healthy. Attempting to fix..."
            remove_container
            sleep 2
            start_container
        else
            print_message $GREEN "✓ OnlyOffice is already healthy"
        fi
        ;;
    *)
        echo "Usage: $0 {start|stop|restart|status|logs|remove|health|auto-fix}"
        echo ""
        echo "Commands:"
        echo "  start     - Start OnlyOffice container"
        echo "  stop      - Stop OnlyOffice container"
        echo "  restart   - Restart OnlyOffice container"
        echo "  status    - Show container status and health"
        echo "  logs      - Show container logs"
        echo "  remove    - Remove container completely"
        echo "  health    - Check if OnlyOffice is responding"
        echo "  auto-fix  - Automatically fix issues if detected"
        exit 1
        ;;
esac

exit 0