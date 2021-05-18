<?php

/**
 * Script to download recordings from Dial9, a VOIP service: http://www.dial9.co.uk/
 * Simply change the 4 variables below and run: php /path/to/dial9.php
 * @author James Hadley https://www.loadingdeck.com/labs/
 * @license The MIT License
 */

// Dial9 username
define('USERNAME', '');

// Dial9 password
define('PASSWORD', '');

// Storage directory
define('DIRECTORY', '/backup/dial9');

// 0 = keep files at Dial9, 1 = remove them after downloading
define('REMOVE', 1);

class Dial9Downloader
{
    public function run()
    {
        printf("Running at %s\n", (new \DateTime())->format('Y-m-d H:i:s'));

        // Get list of calls
        foreach(array('incoming', 'external') as $type)
        {
            printf("*** %s CALLS ***\n", strtoupper($type));
            $page = 1;
            unset($logs);
            while(!isset($logs) || count($logs['data']))
            {
                $logs = $this->curlDial9('logs/historical', array('type' => $type, 'page' => $page));
                if($logs === false)
                {
                    // alert fail
                    printf("Could not get call logs:\n");
                    continue 2;
                }
                $logs = json_decode($logs, true);
                if($logs['status'] != 'success')
                {
                    // alert fail
                    printf("Call log return value was not success\n");
                    continue 2;
                }
                foreach($logs['data'] as $call)
                {
                    printf("* CALL ID %d\n", $call['id']);
                    if($call['has_recording?'] !== false)
                    {
                        printf("* HAS RECORDING\n", $call['id']);

                        // Download the recording
                        $recording = $this->curlDial9('logs/recording', array('id' => $call['id']));
                        if($recording === false) 
                        {
                            // alert fail
                            printf("Did not get a value for recording\n");
                            continue 3;
                        }
                        $recording = json_decode($recording, true);
                        if($recording['status'] != 'success')
                        {
                            // alert fail
                            printf("Could not download call %s\n", $call['id']);
                            continue;
                        }

                        $time = str_replace(':', '-', substr($call['timestamp'], 11, 8));
                        $file_name = sprintf('backups/%s.wav', $call['id']);
                        if(file_exists($file_name) && filesize($file_name) > 0) {
                            printf("Already downloaded %d\n", $call['id']);
                            if(REMOVE == 1){
                              printf("deleting call %d\n", $call['id']);
                              $res = $this->curlDial9('logs/delete_recording', array('id' => $call['id']));
                            }
                        } else {
                            printf("Downloading call %d\n", $call['id']);
                            file_put_contents(
                                $file_name,
                                base64_decode($recording['data']['file'])
                            );
                        }             
                    } else {
                      printf("* NO RECORDING\n", $call['id']);
                    }
                }
                $page++;
            }
        }
    }
    private function curlDial9($function, array $params = array())
    {
        $json = sprintf('params=%s', json_encode($params));
        $ch   = curl_init();
        curl_setopt($ch, CURLOPT_URL, sprintf('https://connect.dial9.co.uk/api/v2/%s', $function));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER,
            array(
                sprintf('X-Auth-Token: %s', USERNAME),
                sprintf('X-Auth-Secret: %s', PASSWORD)
            )
        );
        $r = curl_exec($ch);

        if(curl_errno($ch) !== 0) {
            printf("Curl error: %d: %s\n", curl_errno($ch), curl_error($ch));
        } else {
            return $r;
        }
    }
}
(new Dial9Downloader())->run();
