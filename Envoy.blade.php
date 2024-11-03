@setup
    $testingServers = [
        'edi_test' => 'ec2-user@52.196.197.196',
        'nk2_test' => 'ec2-user@52.193.194.197',
    ];
    $productionServers = [
        'hrnb_real' => 'ec2-user@13.115.211.89',
        'edi_real' => 'ec2-user@54.199.177.0',
        'nk2_real' => 'ec2-user@13.230.84.86',
        'api_real' => 'ec2-user@52.199.5.86',
        'server71' => 'ec2-user@52.69.79.71',
        'server81' => 'ec2-user@52.68.23.81',
        'redmine' => 'ec2-user@52.197.195.9',
        'toranote' => 'ubuntu@3.114.152.180',
        'toranote_admin' => 'ubuntu@18.182.95.186',
        'new-challenge-a' => 'phamphu232@35.213.111.208',
        'daito-sales-2' => 'phamphu232@35.194.235.203',
        'gitlab-daito' => 'phamphu232@gitlab.monotos.biz',
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