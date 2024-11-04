# SERVER-TOOLS

## Setup Client(local)

```
cd ~
git clone https://github.com/phamphu232/server-tools.git .server-tools
composer install
```

## Deploy

```
# Deploy only to testing servers
php vendor/bin/envoy run deploy_test

# Deploy only to production servers
php vendor/bin/envoy run deploy_prod

# Deploy to all servers
php vendor/bin/envoy run deploy

# Set crontab
## sudo vi /etc/crontab

## Client machine
* * * * * ec2-user /home/ec2-user/.server-tools/app/client/schedule.sh 'http://localhost/server-tools/server-tools/server.php' > /home/ec2-user/.server-tools/schedule.log 2>&1
# OR
* * * * * ubuntu /home/ec2-user/.server-tools/app/client/schedule.sh 'http://localhost/server-tools/server-tools/server.php' > /home/ec2-user/.server-tools/schedule.log 2>&1

## Server machine
* * * * * ubuntu cd ~/.server-tools && sh ./app/server/schedule.sh > schedule_server.log 2>&1
```

## Useful command

```
# Get current server IP
curl ifconfig.me

# Generate SSH Key
ssh-keygen -t rsa -b 4096 -N "" -f ~/.ssh/id_rsa

# Copy SSH Key to clipboard
cat ~/.ssh/id_rsa.pub

# Add SSH Key to Github
ssh-keygen -f "~/.ssh/known_hosts" -R 8.8.8.8
```