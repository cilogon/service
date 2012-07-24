<?php

/************************************************************************
 * Class name : timeit                                                  *
 * Description: This class allows for the timing of PHP code.           *
 *                                                                      *
 * Example usage:                                                       *
 *    require_once('timeit.php');                                       *
 *    $timeit = new timeit('/tmp/myprogramtime.txt');                   *
 *    $timeit->printTime('Program Start...');                           *
 *        ... your program code here ...                                *
 *    $timeit->printTime('Program End  ...');                           *
 *                                                                      *
 * Example output of '/tmp/myprogramtime.txt':                          *
 *    Program Start...        0.0004          0.0140          0.0136    *
 *    Program End  ...        0.0141          0.0142          0.0001    *
 *                                                                      *
 * First column is the label parameter of the printTime() method.       *
 * Second column the current 'program time'.                            *
 * Third column is the time difference between the current line and the *
 *    next line (or the end of the script when the file is closed).     *
 * Fourth column is the difference between the second and third columns.*
 ************************************************************************/

class timeit {
    
    /* Set the constants to correspond to your particular set up.       */
    const defaultFilename = "/tmp/timing.txt";

    /* These variables should be accessed only by internal methods.  */
    protected $timingfilename;  // File name of the timing file.
    protected $fh=null;         // File handle of the timing file.
    protected $firsttime;       // Time when object is constructed.
    protected $lasttime=0;      // Last time getTime() was called.
    protected $firstlineprinted=false;

    /********************************************************************
     * Function  : __construct - default constructor                    *
     * Parameters: (1) The name of the file to write timings to.        *
     *                 Defaults to '/tmp/timing.txt'.                   *
     *             (2) If true, append to file (and create if needed).  *
     *                 If false, rewrite timing file from scratch.      *
     * Returns   : A new timeit object.                                 *
     * Default constructor.  Sets the filename for the timing file,     *
     * opens the file with the appropriate mode (append or rewrite),    *
     * and sets the internal timing values for printout later.          *
     ********************************************************************/
    function __construct($filename=self::defaultFilename,$append=false) {
        $this->setFilename($filename);
        $this->openFile($append);
        $this->firsttime = $this->getTime();
    }

    /********************************************************************
     * Function  : __destruct                                           *
     * Default destructor.  Prints out the last timing value to the     *
     * timing file, and then closes the timing file.                    *
     ********************************************************************/
    function __destruct() {
        if ($this->firstlineprinted) {
            $currtime = $this->getTime() - $this->firsttime;
            fprintf($this->fh, "%8.4f" . "\t" . "%8.4f" . "\n",
                               $currtime,($currtime-$this->lasttime));
        }
        $this->closeFile();
    }

    /********************************************************************
     * Function  : getFilename                                          *
     * Returns   : The name of the file to write timings to.            *
     * This function returns a string of the full path of the file to   *
     * which timing info is written.                                    *
     ********************************************************************/
    function getFilename() {
        return $this->timingfilename;
    }

    /********************************************************************
     * Function  : setFilename                                          *
     * Parameter : The name of the timing file.  Defaults to            *
     *             '/tmp/timing.txt'.                                   *
     * This function sets the string of the full path of the file to    *
     * which timing info is written.                                    *
     ********************************************************************/
    function setFilename($filename=self::defaultFilename) {
        $this->timingfilename = $filename;
    }

    /********************************************************************
     * Function  : openFile                                             *
     * Parameter : If true, append to file (and create if needed).  If  *
     *             false, rewrite timing file from scratch.             *
     * Return    : True if opened file successfully. False otherwise.   *
     * This function opens the timing file with the appropriate mode    *
     * (append or write from scratch). The file handle is stored in the *
     * protected member variable $fh. If there is a problem opening     *
     * the file, the file handle is set to null and false is returned.  *
     ********************************************************************/
    function openFile($append=false) {
        $retval = true;  // Assume successfully opened file
        $this->fh = fopen($this->getFilename(),($append ? 'a' : 'w'));
        if (!$this->fh) {
            $this->fh = null;
            $retval = false;
        }
        return $retval;
    }

    /********************************************************************
     * Function  : closeFile                                            *
     * This function closes the timing file if it is open.              *
     ********************************************************************/
    function closeFile() {
        if (!is_null($this->fh)) {
            fclose($this->fh);
            $this->fh = null;
        }
    }

    /********************************************************************
     * Function  : getTime                                              *
     * Parameter : The number of decimal places (i.e. precision).       *
     * Returns   : The current Unix timestamp with microseconds, using  *
     * the specified number of decimal places.                          *
     ********************************************************************/
    function getTime($precision = 4) {
        return round(microtime(true),$precision);
    }

    /********************************************************************
     * Function  : printTime                                            *
     * Parameter : A string to prepend to the timing line in the file.  *
     * This is the main function of this class. It prints out a line    *
     * to the timing file.  The first time this function is called, it  *
     * prints out the specified "label" string to the file followed     *
     * by the current "program time" (which is the difference between   *
     * the current clock time and the time the object was initialized)  *
     * which should be close to zero. Each subsequent time this method  *
     * is called will append two more numbers to the previous line: the *
     * new current "program time" and the difference between the new    *
     * and old program times.  This gives the user an indication of     *
     * how long it took between to printTime()s.                        *
     ********************************************************************/
    function printTime($label) {
        if ($this->firstlineprinted) {
            $currtime = $this->getTime() - $this->firsttime;
            fprintf($this->fh, "%8.4f" . "\t" . "%8.4f" . "\n",
                               $currtime,($currtime-$this->lasttime));
        }
        $this->lasttime = $this->getTime() - $this->firsttime;
        fprintf($this->fh, $label . "\t" . "%8.4f" . "\t", $this->lasttime);
        $this->firstlineprinted = true;
    }

}

?>
