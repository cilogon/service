<?

/************************************************************************
 * Class name : timeit                                                  *
 * Description: This class allows for the timing of PHP code.           *
 *                                                                      *
 * Example usage:                                                       *
 ************************************************************************/

class timeit {

    const filename = "/tmp/timings.txt";

    protected $fh;
    protected $firsttime;
    protected $lasttime;

    /********************************************************************
     * Function  : __construct - default constructor                    *
     * Returns   : A new timeit object.                                 *
     * Default constructor.  
     ********************************************************************/
    function __construct($filename=self::filename) {
        $this->fh = fopen($filename,'w');
        $this->firsttime = $this->getTime();
        $this->lasttime = 0;
    }

    /********************************************************************
     * Function  : __destruct
     * Default destructor.  
     ********************************************************************/
    function __destruct() {
        if ($this->lasttime > 0) {
            $currtime = $this->getTime() - $this->firsttime;
            fprintf($this->fh, "%8.4f" . "\t" . "%8.4f" . "\n",
                               $currtime,($currtime-$this->lasttime));
        }
        fclose($this->fh);
    }

    /********************************************************************
     * Function  : getTime                                              *
     * Parameter :
     * Returns   : 
     ********************************************************************/
    function getTime($precision = 4) {
        return round(microtime(true),$precision);
    }

    /********************************************************************
     * Function  : printTime                                            *
     * Returns   : 
     ********************************************************************/
    function printTime($label) {
        if ($this->lasttime > 0) {
            $currtime = $this->getTime() - $this->firsttime;
            fprintf($this->fh, "%8.4f" . "\t" . "%8.4f" . "\n",
                               $currtime,($currtime-$this->lasttime));
        }
        $this->lasttime = $this->getTime() - $this->firsttime;
        fprintf($this->fh, $label . "\t" . "%8.4f" . "\t", $this->lasttime);
    }

}

?>
