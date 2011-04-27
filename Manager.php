<?php
/**
 * @author Roman Nowicki <peengle@gmail.com>
 * @license GNU General Public Licence
 */
require_once('Task.php');
require_once('Thread.php');
class ManagerException extends Exception {}
class Manager {

    private $cron_file;
    private $cron_table;
    private $handler;
    private $log_execution_file; //file when information about executed task are holded
    private $quiet_mode = false;

    public static $process_path = '/proc'; //path to direcotry where all information about processes are hold

    public function __construct($cron_file, $log_execution_file = null)
    {
        $this->validateCronFile($cron_file);

        if($log_execution_file)
            $this->log_execution_file = $log_execution_file;
        else {
            $this->log_execution_file = pathinfo($cron_file, PATHINFO_DIRNAME) . '/crontab.execution.log';
        }

        if (!is_file($this->log_execution_file)) {
            $h = fopen($this->log_execution_file, 'w');
            fwrite($h, "id;lastExecuted;PID\n");
            fclose($h);
        }

        $this->cron_file = $cron_file;

        $this->parseCronFile();
    }

    public function setQuietMode($val) {
        if (!is_bool($val)) throw new ManagerException('First argument must be type boolean');
        $this->quiet_mode = $val;
    }

    private function validateCronFile($cron_file) {
        if(!is_file($cron_file))
            throw new ManagerException('First argument must be valid crontab file');

        if(!is_readable($cron_file))
            throw new ManagerException('Cannot read crontab file, probably problem with permissions');


        $json_errors = array(
            JSON_ERROR_NONE => 'No error has occurred',
            JSON_ERROR_DEPTH => 'The maximum stack depth has been exceeded',
            JSON_ERROR_CTRL_CHAR => 'Control character error, possibly incorrectly encoded',
            JSON_ERROR_SYNTAX => 'Syntax error',
        );

        if(!json_decode(file_get_contents($cron_file)))
            throw new ManagerException(sprintf('%s is invalid error: %s', $cron_file, $json_errors[json_last_error()]));
    }

    private function parseCronFile() {
        $this->cron_table = json_decode(file_get_contents($this->cron_file));
    }


    public function executeAll() {
        $threads = array();
        foreach($this->cron_table as $task)
        {
            $lastExecuted = $this->getExecutionTimeForTaskId($task->id);

            if($pid = $this->getPIDForTaskId($task->id)) {
                if(self::isProcessRunning($pid)) {
                    $this->flush('Process (%s) is still running for task %s', $pid, $task->id);
                    continue;
                }
            }

            $Task = new Task($task, $lastExecuted); 
            if($Task->execute() === false)
                continue;
                
            $lastExecuted = $Task->getLastExecuted();
            $PID = $Task->getProcessId();
            $this->updateTaskExecutionLog($task->id, $lastExecuted, $PID);

            $threads[] = $Task->getThread();
        }
        
        $this->flush('We have %s process(es):', count($threads));
        $process_running = count($threads);

        foreach($threads as $Thread)
        {
            $this->flush('PID: %s -> %s', $Thread->getProcessId(), $Thread->getCommand());
        }

        while($process_running)
        {
            foreach($threads as $key => $Thread)
            {
                if(!$Thread->isRunning())
                {
                    $this->flush('Process %s finished', $Thread->getProcessId());
                    $process_running--;
                    unset($threads[$key]);
                }
            }
        }
    }

    private function flush($msg) {
        if($this->quiet_mode) 
            return;

        echo call_user_func_array('sprintf', func_get_args());
        echo "\n";
    }

    private function getLogExecutionData($task_id) {
        $content = file($this->log_execution_file);

        foreach($content as $line)
        {
            $row = explode(";", $line);
            if($row[0] == $task_id) {
                return $row;
            }
        }

        return null;
    }

    private function getPIDForTaskId($task_id) {
        if($data = $this->getLogExecutionData($task_id)) {
            return $data[2];
        }
        return null;
    }

    private function getExecutionTimeForTaskId($task_id) {
        if($data = $this->getLogExecutionData($task_id)) {
            return $data[1];
        }

        return null;
    }

    private function updateTaskExecutionLog($id, $time, $pid) {
        $content = file($this->log_execution_file, FILE_SKIP_EMPTY_LINES);

        $found = false;
        for($i = 0; $i < count($content); $i++) {
            $row = explode(';', $content[$i]);
            if($row[0] == $id) {
                $content[$i] = "$id;$time;$pid\n";
                $found = true;
            }
        }

        if (!$found) {
            $content[] = "$id;$time;$pid\n";
        }

        file_put_contents($this->log_execution_file, $content);
    }

    static public function isProcessRunning($pid) {
        $path = self::$process_path . '/' . $pid;

        if( ! is_dir($path) )
            return false;

        $file = $path . '/status';

        if(! file_exists($file) )
            return false;

        foreach(file($file) as $line) {
            $data = explode(':', $line);
            list($key, $value) = $data;
            $value = trim($value);

            if($key == 'State' && $value == 'R (running)') {
                return true;
            }
        }

        return false;
    }

}
