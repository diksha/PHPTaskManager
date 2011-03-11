<?php
class Task
{
    private $_LastExecuted; //updated automaticly on task execute
    private $_PID; //process id updated automaticly on task execute

    //this we setup in config file
    private $id; //this index is used to save information about last execution time in crontab.exec.log
    private $disabled; //when set task will not run
    private $PeriodInSeconds; 
    private $Command;
    private $StartHour;
    private $EndHour;
    private $StartDate;
    private $EndDate;
    private $Days = array(); //1 (for Monday) through 7 (for Sunday)
    private $Months = array(); //1 through 12
    private $LogFile;

    private $Thread; //CrontProcess

    public function __construct(stdClass $task, $lastExecuted = null) {
        $this->_LastExecuted = $lastExecuted;

        foreach($task as $key => $value)
            $this->$key = $value;
    }

    public function __set($name, $value) {
        throw new InvalidArgumentException(sprintf('Invalid property "%s" for Task', $name));
    }

    public function getProcessId() {
        return $this->_PID;
    }

    public function getLastExecuted() {
        return $this->_LastExecuted;
    }

    public function getThread() {
        return $this->Thread;
    }

    public function execute() {
        if(!$this->shouldTaskBeRun())
            return false;

        $this->Thread = $Thread = new Thread();
        $Thread->setCommand($this->Command);

        if($this->LogFile)
            $Thread->setLogFile($this->LogFile);

        $Thread->execute();

        $this->_LastExecuted = date('Y-m-d H:i:s', time());
        $this->_PID = $Thread->getProcessId();
    }

    private function shouldTaskBeRun() {
        if($this->disabled)
            return false;

        if(!$this->isTimeoutExpired())
            return false;
        
        if(!$this->isInHourRange())
            return false;

        if(!$this->isInDateRange())
            return false;

        if(!$this->isInDaysRange())
            return false;

        if(!$this->isInMonthsRange())
            return false;

        return true;
    }

    private function isInDaysRange() {
        if($this->Days && !in_array(date('N', time()), $this->Days))
            return false;

        return true;
    }

    private function isInMonthsRange() {
        if($this->Months && !in_array(date('n', time()), $this->Months))
            return false;

        return true;
    }
    
    private function isInDateRange() {
        if($this->StartDate !== null && (time() < strtotime($this->StartDate)))
            return false;

        if($this->EndDate !== null && (time() > strtotime($this->EndDate)))
            return false;

        return true;
    }

    private function isInHourRange() {
        if($this->StartHour !== null  && (time() < strtotime(date('Y-m-d '.$this->StartHour))))
            return false;

        if($this->EndHour !== null && (time() > strtotime(date('Y-m-d '.$this->EndHour))))
            return false;

        return true;
    }

    private function isTimeoutExpired() {
        if(time() <= (strtotime($this->_LastExecuted) + $this->PeriodInSeconds))
            return false;

        return true;
    }
}
