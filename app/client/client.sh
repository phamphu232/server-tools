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

CURL_OPTS="-s --max-time 10"
GCP_METADATA="http://metadata.google.internal/computeMetadata/v1"
AWS_METADATA="http://169.254.169.254/latest/meta-data"

safe_get_public_ip() {
    local ip="$1"
    if [[ -z "$ip" || "$ip" == *"<html"* || "$ip" == *"<!DOCTYPE"* || ! "$ip" =~ ^[0-9]+(\.[0-9]+){3}$ ]]; then
        echo ""
    else
        echo "$ip"
    fi
}

# Check for GCP
if curl $CURL_OPTS "$GCP_METADATA/instance/name" -H "Metadata-Flavor: Google" >/dev/null 2>&1; then
    PLATFORM="GCP"
    ZONE_CODE=$(curl $CURL_OPTS "$GCP_METADATA/instance/zone" -H "Metadata-Flavor: Google" | awk -F'/' '{print $NF}')
    INSTANCE_NAME=$(curl $CURL_OPTS "$GCP_METADATA/instance/name" -H "Metadata-Flavor: Google")
    PROJECT_ID=$(curl $CURL_OPTS "$GCP_METADATA/project/project-id" -H "Metadata-Flavor: Google")
    RAW_IP=$(curl $CURL_OPTS "$GCP_METADATA/instance/network-interfaces/0/access-configs/0/external-ip" -H "Metadata-Flavor: Google")
    PUBLIC_IP=$(safe_get_public_ip "$RAW_IP")

# Check for AWS
elif TOKEN=$(curl $CURL_OPTS -X PUT "$AWS_METADATA/../api/token" \
    -H "X-aws-ec2-metadata-token-ttl-seconds: 21600") && \
    curl $CURL_OPTS -H "X-aws-ec2-metadata-token: $TOKEN" "$AWS_METADATA/instance-id" >/dev/null 2>&1; then

    PLATFORM="AWS"
    ZONE_CODE=$(curl $CURL_OPTS -H "X-aws-ec2-metadata-token: $TOKEN" "$AWS_METADATA/placement/region")
    INSTANCE_ID=$(curl $CURL_OPTS -H "X-aws-ec2-metadata-token: $TOKEN" "$AWS_METADATA/instance-id")
    RAW_IP=$(curl $CURL_OPTS -H "X-aws-ec2-metadata-token: $TOKEN" "$AWS_METADATA/public-ipv4")
    PUBLIC_IP=$(safe_get_public_ip "$RAW_IP")

# Fallback
else
    PLATFORM="UNKNOWN"
    INSTANCE_ID=$(hostname)
    RAW_IP=$(curl -4 -s http://ifconfig.me)
    PUBLIC_IP=$(safe_get_public_ip "$RAW_IP")
fi

# CPU_TOP=$(ps -eo pid,ppid,%cpu,cmd --sort=-%cpu | head -n 31 | awk '{printf "%s\\n", $0}')
CPU_TOP=$(ps -eo pid,ppid,%cpu,cmd --sort=-%cpu | head -n 31 | awk '{gsub(/\\/, "\\\\", $0); gsub(/"/, "\\\"", $0); printf "%s\\n", $0}')

# RAM_TOP=$(ps -eo pid,ppid,%mem,cmd --sort=-%mem | head -n 31 | awk '{printf "%s\\n", $0}')
RAM_TOP=$(ps -eo pid,ppid,%mem,cmd --sort=-%mem | head -n 31 | awk '{gsub(/\\/, "\\\\", $0); gsub(/"/, "\\\"", $0); printf "%s\\n", $0}')

CPU=$(top -bn1 | grep -i "Cpu(s)")

RAM=$(free -k | grep -i "Mem")

DISK=$(df -k | grep -i "/$")

TIMESTAMP=$(date +%s)

USERNAME=$(whoami)

VERIFY_CODE=$(echo -n "VERIFY_CODE:${TIMESTAMP}_${PLATFORM}_${PUBLIC_IP}" | md5sum | awk '{print $1}')

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
    "VERIFY_CODE": "$VERIFY_CODE",
    "CPU_TOP": "$CPU_TOP",
    "RAM_TOP": "$RAM_TOP"
}
EOF
)

curl -X POST -H "Content-Type: application/json" -d "$DATA" "$SERVER_URL"
