<?php

namespace CILogon\Service;

/**
 * TimeIt
 *
 * This class allows for the timing of PHP code.
 *
 * Example usage:
 *    require_once 'TimeIt.php';
 *    $timeit = new TimeIt('/tmp/myprogramtime.txt');
 *    $timeit->printTime('Program Start...');
 *        ... your program code here ...
 *    $timeit->printTime('Program End  ...');
 *
 * Example output of '/tmp/myprogramtime.txt':
 *    Program Start...        0.0004          0.0140          0.0136
 *    Program End  ...        0.0141          0.0142          0.0001
 *
 * First column is the label parameter of the printTime() method.
 * Second column the current 'program time'.
 * Third column is the time difference between the current line and the
 *    next line (or the end of the script when the file is closed).
 * Fourth column is the difference between the second and third columns.
 */
class TimeIt
{
    /**
     * @var string DEFAULTFILENAME  Set the output file location to
     *      correspond to your particular set up.
     */
    const DEFAULTFILENAME = "/tmp/timing.txt";

    /**
     * @var string $timingfilename File name of the timing file
     */
    protected $timingfilename;

    /**
     * @var resource $fh The file handle of the timing file
     */
    protected $fh = null;

    /**
     * @var float $firsttime Time when object is constructed
     */
    protected $firsttime;

    /**
     * @var float $lasttime Last time getTime() was called
     */
    protected $lasttime = 0;

    /**
     * @var bool $firstlineprinted Print the first line only once.
     */
    protected $firstlineprinted = false;

    /**
     * __construct
     *
     * Default constructor.  Sets the filename for the timing file,
     * opens the file with the appropriate mode (append or rewrite),
     * and sets the internal timing values for printout later.
     *
     * @param string $filename (Optional) The name of the file to write
     *        timings to. Defaults to '/tmp/timing.txt'.
     * @param bool $append (Optional) If true, append to file (and create if
     *        needed). If false, rewrite timing file from scratch. Defaults
     *        to false.
     * @return TimeIt A new TimeIt object.
     */
    public function __construct(
        $filename = self::DEFAULTFILENAME,
        $append = false
    ) {
        $this->setFilename($filename);
        $this->openFile($append);
        $this->firsttime = $this->getTime();
    }

    /**
     * __destruct
     *
     * Default destructor.  Prints out the last timing value to the
     * timing file, and then closes the timing file.
     */
    public function __destruct()
    {
        if ($this->firstlineprinted) {
            $currtime = $this->getTime() - $this->firsttime;
            fprintf(
                $this->fh,
                "%8.4f" . "\t" . "%8.4f" . "\n",
                $currtime,
                ($currtime-$this->lasttime)
            );
        }
        $this->closeFile();
    }

    /**
     * getFilename
     *
     * This function returns a string of the full path of the file to
     * which timing info is written.
     *
     * @return string The name of the file to write timings to.
     */
    public function getFilename()
    {
        return $this->timingfilename;
    }

    /**
     * setFilename
     *
     * This function sets the string of the full path of the file to
     * which timing info is written.
     *
     * @param string $filename (Optional) The name of the timing file.
     *        Defaults to '/tmp/timing.txt'.
     */
    public function setFilename($filename = self::DEFAULTFILENAME)
    {
        $this->timingfilename = $filename;
    }

    /**
     * openFile
     *
     * This function opens the timing file with the appropriate mode
     * (append or write from scratch). The file handle is stored in the
     * protected member variable $fh. If there is a problem opening
     * the file, the file handle is set to null and false is returned.
     *
     * @param bool $append (Optional) If true, append to file (and create if
     *        needed).  If false, rewrite timing file from scratch. Defaults
     *        to false.
     * @return bool True if opened file successfully. False otherwise.
     */
    public function openFile($append = false)
    {
        $retval = true;  // Assume successfully opened file
        $this->fh = fopen($this->getFilename(), ($append ? 'a' : 'w'));
        if (!$this->fh) {
            $this->fh = null;
            $retval = false;
        }
        return $retval;
    }

    /**
     * closeFile
     *
     * This function closes the timing file if it is open.
     */
    public function closeFile()
    {
        if (!is_null($this->fh)) {
            fclose($this->fh);
            $this->fh = null;
        }
    }

    /**
     * getTime

     * @param int $precision (Optional) The number of decimal places (i.e.
     *        precision). Defaults to 4.
     * @return float The current Unix timestamp with microseconds, using
     *         the specified number of decimal places.
     */
    public function getTime($precision = 4)
    {
        return round(microtime(true), $precision);
    }

    /**
     * printTime
     *
     * This is the main function of this class. It prints out a line
     * to the timing file.  The first time this function is called, it
     * prints out the specified 'label' string to the file followed
     * by the current 'program time' (which is the difference between
     * the current clock time and the time the object was initialized)
     * which should be close to zero. Each subsequent time this method
     * is called will append two more numbers to the previous line: the
     * new current 'program time' and the difference between the new
     * and old program times.  This gives the user an indication of
     * how long it took between to printTime()s.
     *
     * @param string $label A string to prepend to the timing line in the file.
     */
    public function printTime($label)
    {
        if ($this->firstlineprinted) {
            $currtime = $this->getTime() - $this->firsttime;
            fprintf(
                $this->fh,
                "%8.4f" . "\t" . "%8.4f" . "\n",
                $currtime,
                ($currtime-$this->lasttime)
            );
        }
        $this->lasttime = $this->getTime() - $this->firsttime;
        fprintf($this->fh, $label . "\t" . "%8.4f" . "\t", $this->lasttime);
        $this->firstlineprinted = true;
    }
}
