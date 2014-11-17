<?php
    require_once 'vendor/autoload.php';
    use Symfony\Component\Yaml\Yaml;

    $logger = new Katzgrau\KLogger\Logger(__DIR__.'/logs');

    // load and parse hook config file
    $config_file_path = __DIR__. '/_config.yml';

    $config = Yaml::parse($config_file_path);

    $logger->info("--------------- a new hook ----------------");

    // read gitlab post body
    $body = file_get_contents('php://input');
    $logger->debug("hook body:\n". $body);

    // parse gitlab payload
    $payload = json_decode($body,true);
    $logger->debug('hook payload', $payload);

    // get project name
    $project = $_REQUEST['p'];

    $logger->debug("hook project: " .$project);

    // get project config
    $project_config = $config[$project];

    if ($project_config)
    {
        $logger->debug("hook config for '" . $project . "'", $project_config);

        $hookClass = $project_config['hook-class'];
        require_once 'hooks/class.' . $hookClass . '.php';
        $hook = new $hookClass($project, $project_config, $payload);

        $hook->run();
    }
    else
    {
        $logger->debug("hook config for '". $project . "' not found");
    }




