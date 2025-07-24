#!/bin/bash

# Installation script for WireGuard Monitor Service
set -e

SCRIPT_NAME="wg0_monitor.py"
SERVICE_NAME="wg0-monitor.service"
INSTALL_DIR="/usr/local/bin"
SERVICE_DIR="/etc/systemd/system"

echo "Installing WireGuard Monitor Service..."

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   echo "This script must be run as root (use sudo)" 
   exit 1
fi

# Create the Python script
cat > "$INSTALL_DIR/$SCRIPT_NAME" << 'EOF'
#!/usr/bin/env python3
"""
WireGuard wg0 monitoring script
Checks if wg0 is up, restarts if down, reboots after 10 failed attempts
"""

import subprocess
import time
import logging
import sys
import os
from datetime import datetime

# Configuration
INTERFACE = "wg0"
MAX_ATTEMPTS = 10
DELAY = 120
LOG_FILE = "/var/log/wg0_monitor.log"

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler(LOG_FILE),
        logging.StreamHandler(sys.stdout)
    ]
)

logger = logging.getLogger(__name__)

class WireGuardMonitor:
    def __init__(self):
        self.attempt_count = 0
        logger.info(f"Starting WireGuard monitor for {INTERFACE}")
    
    def check_interface_up(self):
        """Check if WireGuard interface is up and running"""
        try:
            # Check if interface exists and is UP
            result = subprocess.run(
                ['ip', 'link', 'show', INTERFACE],
                capture_output=True,
                text=True,
                check=True
            )
            
            # Check if interface state is UP
            if 'UP' in result.stdout:
                return True
            else:
                return False
                
        except subprocess.CalledProcessError:
            # Interface doesn't exist
            return False
        except Exception as e:
            logger.error(f"Error checking interface status: {e}")
            return False
    
    def restart_wireguard(self):
        """Attempt to restart WireGuard interface"""
        logger.info(f"Attempting to restart WireGuard interface {INTERFACE}")
        
        try:
            # Stop the interface
            subprocess.run(
                ['wg-quick', 'down', INTERFACE],
                capture_output=True,
                check=False  # Don't raise exception if already down
            )
            
            time.sleep(2)
            
            # Start the interface
            result = subprocess.run(
                ['wg-quick', 'up', INTERFACE],
                capture_output=True,
                text=True,
                check=True
            )
            
            logger.info(f"Successfully restarted {INTERFACE}")
            return True
            
        except subprocess.CalledProcessError as e:
            logger.error(f"Failed to restart {INTERFACE}: {e.stderr}")
            return False
        except Exception as e:
            logger.error(f"Unexpected error restarting WireGuard: {e}")
            return False
    
    def reboot_system(self):
        """Reboot the system after warning delay"""
        logger.warning(f"Maximum restart attempts ({MAX_ATTEMPTS}) reached. Rebooting system in 30 seconds...")
        time.sleep(30)
        
        try:
            subprocess.run(['reboot'], check=True)
        except Exception as e:
            logger.error(f"Failed to reboot system: {e}")
            sys.exit(1)
    
    def monitor_loop(self):
        """Main monitoring loop"""
        while True:
            try:
                if self.check_interface_up():
                    logger.info(f"{INTERFACE} is up and running")
                    self.attempt_count = 0  # Reset counter when interface is working
                else:
                    logger.warning(f"{INTERFACE} is down")
                    self.attempt_count += 1
                    logger.info(f"Restart attempt {self.attempt_count} of {MAX_ATTEMPTS}")
                    
                    if self.restart_wireguard():
                        self.attempt_count = 0  # Reset counter on successful restart
                    else:
                        if self.attempt_count >= MAX_ATTEMPTS:
                            self.reboot_system()
                
                time.sleep(DELAY)
                
            except KeyboardInterrupt:
                logger.info("Monitor stopped by user")
                sys.exit(0)
            except Exception as e:
                logger.error(f"Unexpected error in monitoring loop: {e}")
                time.sleep(DELAY)

def check_privileges():
    """Check if running with root privileges"""
    if os.geteuid() != 0:
        print("This script requires root privileges to restart WireGuard and reboot the system.")
        print("Please run with: sudo python3 wg0_monitor.py")
        sys.exit(1)

def main():
    check_privileges()
    monitor = WireGuardMonitor()
    monitor.monitor_loop()

if __name__ == "__main__":
    main()
EOF

# Create the systemd service file
cat > "$SERVICE_DIR/$SERVICE_NAME" << 'EOF'
[Unit]
Description=WireGuard wg0 Interface Monitor
After=network.target wg-quick@wg0.service
Wants=wg-quick@wg0.service
Documentation=man:wg(8) man:wg-quick(8)

[Service]
Type=simple
ExecStart=/usr/bin/python3 /usr/local/bin/wg0_monitor.py
Restart=always
RestartSec=30
User=root
Group=root

# Security settings
NoNewPrivileges=false
PrivateTmp=true
ProtectSystem=strict
ProtectHome=true
ReadWritePaths=/var/log
CapabilityBoundingSet=CAP_NET_ADMIN CAP_SYS_BOOT

# Logging
StandardOutput=journal
StandardError=journal
SyslogIdentifier=wg0-monitor

# Resource limits
LimitNOFILE=1024
MemoryMax=128M

[Install]
WantedBy=multi-user.target
EOF

# Set proper permissions
chmod +x "$INSTALL_DIR/$SCRIPT_NAME"
chmod 644 "$SERVICE_DIR/$SERVICE_NAME"

# Create log directory if it doesn't exist
mkdir -p /var/log
touch /var/log/wg0_monitor.log
chmod 644 /var/log/wg0_monitor.log

# Reload systemd and enable the service
systemctl daemon-reload
systemctl enable "$SERVICE_NAME"

echo "Installation complete!"
echo ""
echo "Available commands:"
echo "  Start service:    sudo systemctl start wg0-monitor"
echo "  Stop service:     sudo systemctl stop wg0-monitor"
echo "  Check status:     sudo systemctl status wg0-monitor"
echo "  View logs:        sudo journalctl -u wg0-monitor -f"
echo "  View log file:    sudo tail -f /var/log/wg0_monitor.log"
echo ""
echo "The service is enabled and will start automatically on boot."
echo "To start it now, run: sudo systemctl start wg0-monitor"
