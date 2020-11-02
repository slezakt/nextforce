<?php
/**
 * Class to provide process management capabilities.
 */

/*
You may use status(), start(), and stop(). notice that start() method gets called automatically one time.
$process = new Process('ls -al');
// or if you got the pid
$process = new Process();
$process.setPid(my_pid);

// Then you can start/stop/ check status of the job.
$process.stop();
$process.start();
if ($process.status()){
echo "The process is currently running";
}else{
echo "The process is not running.";
}

 */
class Process{

    /**
     * @var int
     */
    private $pid;
    /**
     * @var string
     */
    private $command;

    /**
     * @param bool|string $cl command
     */
    public function __construct($cl=false){

        if ($cl != false){
            $this->command = $cl;
            $this->runCom();
        }

    }

    /**
     * execute command
     */
    private function runCom(){

        $command = 'nohup '.$this->command.' > /dev/null 2>&1 & echo $!';
        exec($command ,$op);
        if (empty($op)) {
            throw New Exception('invalid command: '.$command);
        }
        $this->pid = (int)$op[0];

    }

    /**
     * @param $pid
     */
    public function setPid($pid){
        $this->pid = $pid;
    }

    /**
     * @return mixed
     */
    public function getPid(){
        return $this->pid;
    }

    /**
     * @return bool
     */
    public function status(){

        $command = 'ps -p '.$this->pid;
        $op=array();
        exec($command,$op);
        if (!isset($op[1]))return false;
        else return true;

    }

    /**
     * @return bool
     */
    public function start(){

        if ($this->command != '')$this->runCom();
        else return true;

    }

    /**
     * @return bool
     */
    public function stop(){

        $command = 'kill '.$this->pid;
        exec($command);
        if ($this->status() == false)return true;
        else return false;

    }
}
?>
