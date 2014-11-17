<?php
require_once 'vendor/autoload.php';

class GitlabHook
{
    /**
     * @var string Remote server ip to verify the legitimacy of hook
     */
    protected  $_remoteIp = '';

    /**
     * @var string Hook payload. More info see https://gitlab.com/help/web_hooks/web_hooks
     */
    protected $_payload = '';

    /**
     * @var string Object kind of web-hook of gitlab
     */
    protected $_gitlabObjectKind = '';

    /**
     * @var string Hook name also the project name
     */
    protected $_hookName = '';

    /**
     * @var array configuration of web-hook
     */
    protected $_hookConfig = null;

    /**
     * @var Katzgrau\KLogger\Logger Logger
     */
    protected $_logger = null;

    function __construct($name, $config, $payload)
    {
        $this->_hookName = $name;
        $this->_hookConfig = $config;
        $this->_payload = $payload;

        $this->_gitlabObjectKind = $payload['object_kind'];

        $logPath = __DIR__.'/../logs';

        $this->_logger = new Katzgrau\KLogger\Logger($logPath);

        if (
            isset($_SERVER['HTTP_X_FORWARDED_FOR']) &&
            filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP)
        )
        {
            $this->_remoteIp = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        else
        {
            $this->_remoteIp = $_SERVER['REMOTE_ADDR'];
        }

        $this->_logger->debug("remoteIp: ". $this->_remoteIp);
    }

    function isMergeRequestEvent()
    {
        return $this->_gitlabObjectKind == 'merge_request';
    }

    function isIssueEvent()
    {
        return $this->_gitlabObjectKind == 'issue';
    }

    function isGitPushEvent()
    {
        return $this->_gitlabObjectKind == null;
    }


    function verify()
    {
        return true;
    }

    /**
     * Third part service of pushover.com for push notification. See https://pushover.com
     * @param $token
     * @param $user
     * @param $device
     * @param $title
     * @param $msg
     */
    private function sendPushOver($token, $user, $device, $title, $msg)
    {
        $url = 'https://api.pushover.net/1/messages.json';

        $post_data = array(
            'token' => $token,
            'user' => $user,
            'title' => $title,
            'message' => $msg,
        );

        if ($device)
        {
            $post_data['device'] = $device;
        }

        $this->_logger->debug("pushover send post", $post_data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);

        $query = http_build_query($post_data, '', '&');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));

        $output = curl_exec($ch);

        if ($output === FALSE)
        {
            $this->_logger->error( "pushover cURL Error: " . curl_error($ch));
        }
        else
        {
            $this->_logger->debug("pushover result:\n".$output);
        }
        curl_close($ch);
    }

    /**
     * Read push over configuration of pushover, and send msg via pushover.com
     * @param $title
     * @param $msg
     */
    function sendConfiguredPushOver($title, $msg)
    {
        $push_over_config = $this->_hookConfig['push-over'];

        $push_over_token = $push_over_config['token'];
        $push_over_user = $push_over_config['user'];

        $config_devices = $push_over_config["devices"];

        if ($config_devices)
        {
            foreach ($config_devices as $device)
            {
                $this->_logger->debug("send push over to [" .$device."] ",
                    array( "title" => $title,
                        "msg" => $msg));

                $this->sendPushOver($push_over_token, $push_over_user, $device, $title, $msg);
            }
        }
        else
        {
            $this->_logger->debug("send push over to [ALL] ",
                array( "title" => $title,
                    "msg" => $msg));
            $this->sendPushOver($push_over_token, $push_over_user, null, $title, $msg);
        }
    }


    function doHook()
    {
        // do your hook service logic here
    }

    function doHookShell($shell_file_name)
    {
        $shell_path = dirname(__FILE__) . "/../shell/" . $shell_file_name;
        $cmd = "/bin/sh ".$shell_path;
        $output = array();
        $exit = 0;

        $this->_logger->info($cmd);

        exec($cmd, $output, $exit);
    }

    function run()
    {
        if ($this->verify())
        {
            $this->_logger->debug($this->_hookName . " validate TRUE");

            $this->doHook();

            $shell = $this->_hookConfig['hook-shell'];
            if ($shell)
            {
                $this->doHookShell($shell);
            }
        }
        else
        {
            $this->_logger->debug($this->_hookName . " validate FALSE");
        }
    }

}//~ end class