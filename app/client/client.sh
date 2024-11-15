#!/bin/sh

if [ -z "$1" ]; then
  SERVER_URL='http://localhost/server-tools/app/server/server.php'
else
  SERVER_URL="$1"
fi

PLATFORM=""
ZONE_CODE=""
INSTANCE_NAME=""
PROJECT_ID=""
INSTANCE_ID=""
PUBLIC_IP=""

# Check for GCP
if curl -s --max-time 10 http://metadata.google.internal/computeMetadata/v1/instance/name -H "Metadata-Flavor: Google" >/dev/null 2>&1; then
    PLATFORM="GCP"
    ZONE_CODE=$(curl -s --max-time 10 http://metadata.google.internal/computeMetadata/v1/instance/zone -H "Metadata-Flavor: Google" | awk -F'/' '{print $NF}')
    INSTANCE_NAME=$(curl -s --max-time 10 http://metadata.google.internal/computeMetadata/v1/instance/name -H "Metadata-Flavor: Google")
    PROJECT_ID=$(curl -s --max-time 10 http://metadata.google.internal/computeMetadata/v1/project/project-id -H "Metadata-Flavor: Google")
    PUBLIC_IP=$(curl -s --max-time 10 http://metadata.google.internal/computeMetadata/v1/instance/network-interfaces/0/access-configs/0/external-ip -H "Metadata-Flavor: Google")
fi

if [ -z "$PLATFORM" ]; then
    # Check for AWS (IMDSv2-compatible)
    if TOKEN=$(curl -s --max-time 10 -X PUT "http://169.254.169.254/latest/api/token" -H "X-aws-ec2-metadata-token-ttl-seconds: 21600"); then
        if curl -s --max-time 10 -H "X-aws-ec2-metadata-token: $TOKEN" http://169.254.169.254/latest/meta-data/instance-id >/dev/null 2>&1; then
            PLATFORM="AWS"
            ZONE_CODE=$(curl -s --max-time 10 -H "X-aws-ec2-metadata-token: $TOKEN" http://169.254.169.254/latest/meta-data/placement/region)
            INSTANCE_ID=$(curl -s --max-time 10 -H "X-aws-ec2-metadata-token: $TOKEN" http://169.254.169.254/latest/meta-data/instance-id)
            PUBLIC_IP=$(curl -s --max-time 10 -H "X-aws-ec2-metadata-token: $TOKEN" http://169.254.169.254/latest/meta-data/public-ipv4)
        fi
    fi
fi

# Default to UNKNOWN if not AWS or GCP
if [ -z "$PLATFORM" ]; then
    PLATFORM="UNKNOWN"
    INSTANCE_ID=$(hostname)
    PUBLIC_IP=$(curl -4 -s http://ifconfig.me)
fi

CPU_TOP=$(ps -eo pid,ppid,cmd,%mem,%cpu --sort=-%cpu | head -n 31 | awk '{printf "%s\\n", $0}')

RAM_TOP=$(ps -eo pid,ppid,cmd,%mem,%cpu --sort=-%mem | head -n 31 | awk '{printf "%s\\n", $0}')

CPU=$(top -bn1 | grep -i "Cpu(s)")

RAM=$(free -k | grep -i "Mem")

DISK=$(df -k | grep -i "/$")

TIMESTAMP=$(date +%s)

USERNAME=$(whoami)

VERYFY_CODE=$(echo -n "VERIFY_CODE:${TIMESTAMP}_${PLATFORM}_${PUBLIC_IP}" | md5sum | awk '{print $1}')

DATA=$(cat <<EOF
{
    "PLATFORM": "$PLATFORM",
    "ZONE_CODE": "$ZONE_CODE",
    "INSTANCE_NAME": "$INSTANCE_NAME",
    "PROJECT_ID": "$PROJECT_ID",
    "INSTANCE_ID": "$INSTANCE_ID",
    "PUBLIC_IP": "$PUBLIC_IP",
    "CPU": "$CPU",
    "RAM": "$RAM",
    "DISK": "$DISK",
    "USERNAME": "$USERNAME",
    "TIMESTAMP": "$TIMESTAMP",
    "VERIFY_CODE": "$VERYFY_CODE",
    "CPU_TOP": "$CPU_TOP",
    "RAM_TOP": "$RAM_TOP"
}
EOF
)

curl -X POST -H "Content-Type: application/json" -d "$DATA" "$SERVER_URL"