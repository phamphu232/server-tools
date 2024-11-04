@setup
    $testingServers = [
        'server_test1' => 'ec2-user@192.168.1.2',
        'server_test2' => 'ec2-user@192.168.1.3',
    ];
    $productionServers = [
        'server_prod1' => 'ec2-user@192.168.1.4',
        'server_prod2' => 'ec2-user@192.168.1.5',
    ];
    $allServers = array_merge($testingServers, $productionServers);
@endsetup

@servers($allServers)

@task('deployment_testing', ['on' => array_keys($testingServers)])
    eval $(ssh-agent -s)
    ssh-add ~/.ssh/id_rsa
    export GIT_SSH_COMMAND="ssh -o StrictHostKeyChecking=no"
    cd ~
    if [ -d "$HOME/.server-tools/" ]; then
        cd "$HOME/.server-tools/" && git checkout . && git checkout -f main && git pull
    else
        git clone https://github.com/phamphu232/server-tools.git "$HOME/.server-tools/"
    fi
@endtask

@task('deployment_production', ['on' => array_keys($productionServers)])
    eval $(ssh-agent -s)
    ssh-add ~/.ssh/id_rsa
    export GIT_SSH_COMMAND="ssh -o StrictHostKeyChecking=no"
    cd ~
    if [ -d "$HOME/.server-tools/" ]; then
        cd "$HOME/.server-tools/" && git checkout . && git checkout -f main && git pull
    else
        git clone https://github.com/phamphu232/server-tools.git "$HOME/.server-tools/"
    fi
@endtask

@story('deploy_test')
    deployment_testing
@endstory

@story('deploy_prod')
    deployment_production
@endstory

@story('deploy')
    deployment_testing
    deployment_production
@endstory