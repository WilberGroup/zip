<?php namespace Comodojo\Zip;

use \ZipArchive;
use \Comodojo\Exception\ZipException;

/**
 * zip: poor man's php zip/unzip class
 * 
 * @package     Comodojo Spare Parts
 * @author      Marco Giovinazzi <info@comodojo.org>
 * @license     MIT
 *
 * LICENSE:
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

class Zip {
    
    /**
     * Select files to skip
     *
     * @var bool
     */
    private $skip_mode = "NONE";

    /**
     * Supported skip modes
     *
     * @var bool
     */
    private $supported_skip_modes = array("HIDDEN","COMODOJO","ALL","NONE");

    /**
     * Mask for the extraction folder (if it should be created)
     *
     * @var int
     */
    private $mask = 0644;

    /**
     * Internal pointer to zip archive
     */
    private $zip_archive = null;

    private $zip_file = null;

    private $password = null;

    private $path = null;

    /**
     * Array of well known zip status codes
     *
     * @var array
     */
    static private $zip_status_codes = Array(
        ZipArchive::ER_OK           => 'No error',
        ZipArchive::ER_MULTIDISK    => 'Multi-disk zip archives not supported',
        ZipArchive::ER_RENAME       => 'Renaming temporary file failed',
        ZipArchive::ER_CLOSE        => 'Closing zip archive failed',
        ZipArchive::ER_SEEK         => 'Seek error',
        ZipArchive::ER_READ         => 'Read error',
        ZipArchive::ER_WRITE        => 'Write error',
        ZipArchive::ER_CRC          => 'CRC error',
        ZipArchive::ER_ZIPCLOSED    => 'Containing zip archive was closed',
        ZipArchive::ER_NOENT        => 'No such file',
        ZipArchive::ER_EXISTS       => 'File already exists',
        ZipArchive::ER_OPEN         => 'Can\'t open file',
        ZipArchive::ER_TMPOPEN      => 'Failure to create temporary file',
        ZipArchive::ER_ZLIB         => 'Zlib error',
        ZipArchive::ER_MEMORY       => 'Malloc failure',
        ZipArchive::ER_CHANGED      => 'Entry has been changed',
        ZipArchive::ER_COMPNOTSUPP  => 'Compression method not supported',
        ZipArchive::ER_EOF          => 'Premature EOF',
        ZipArchive::ER_INVAL        => 'Invalid argument',
        ZipArchive::ER_NOZIP        => 'Not a zip archive',
        ZipArchive::ER_INTERNAL     => 'Internal error',
        ZipArchive::ER_INCONS       => 'Zip archive inconsistent',
        ZipArchive::ER_REMOVE       => 'Can\'t remove file',
        ZipArchive::ER_DELETED      => 'Entry has been deleted'
    );

    public function __construct($zip_file) {

        if ( empty($zip_file) ) throw new ZipException(self::getStatus(ZipArchive::ER_NOENT));

        $this->zip_file = $zip_file;

    }


    /**
     * Open a zip archive
     *
     * @param   string  $zip_file   ZIP archive
     * @param   bool    $check      (optional) check for archive consistence
     *
     * @return  Object  $this
     */
    static public function open($zip_file) {

        try {

            $zip = new Zip($zip_file);
            
            $zip->setArchive( self::openZipFile($zip_file) );

        }
        catch (ZipException $ze) {

            throw $ze;

        }

        return $zip;

    }

    /**
     * Check a zip archive
     *
     * @param   string  $zip_file   ZIP archive
     * @param   bool    $check      (optional) check for archive consistence
     *
     * @return  Object  $this
     */
    static public function check($zip_file) {

        try {

            $zip = self::openZipFile($zip_file, \ZipArchive::CHECKCONS);

            $zip->close();

        }
        catch (ZipException $ze) {

            throw $ze;

        }

        return true;

    }

    /**
     * Create a new zip archive
     *
     * @param   string  $zip_file   ZIP archive
     *
     * @return  string
     */
    static public function create($zip_file) {

        try {

            $zip = new Zip($zip_file);
            
            $zip->setArchive( self::openZipFile($zip_file, \ZipArchive::CREATE) );

        }
        catch (ZipException $ze) {

            throw $ze;

        }

        return $zip;

    }

    /**
     * Set files to skip
     *
     * @param   string  $mode   HIDDEN, COMODOJO, ALL, NONE
     *
     * @return  Object  $this
     */
    public final function setSkipped($mode) {

        $mode = strtoupper($mode);

        if ( !in_array($mode, $this->supported_skip_modes) ) throw new ZipException("Unsupported skip mode");
        
        $this->skip_mode = $mode;

        return $this;

    }

    public final function getSkipped() {

        return $this->skip_mode;

    }

    public final function setPassword($password) {

        $this->password = $password;

        return $this;

    }

    public final function getPassword() {

        return $this->password;

    }

    public final function setPath($path) {

        if ( !file_exists($path) ) throw new ZipException("Not existent path");

        $this->path = $path[strlen($path)-1] == "/" ? $path : $path . "/";

        return $this;

    }

    public final function getPath() {

        return $this->path;

    } 

    /**
     * Set extraction folder mask
     *
     * @param   int     $mask
     *
     * @return  Object  $this
     */
    public final function setMask($mask) {

        $mask = filter_var($mask, FILTER_VALIDATE_INT, array(
            "options" => array(
                "max_range" => 777,
                "default" => 644 )
            )
        );
        
        $this->mask = $mask;

        return $this;

    }

    public final function getMask() {

        return $this->mask;

    }

    public final function setArchive(ZipArchive $zip) {

        $this->zip_archive = $zip;

        return $this;

    }

    public final function getArchive() {

        return $this->zip_archive;

    }

    /**
     * Close the zip archive
     *
     * @return  bool
     */
    public function close() {


        if ( $this->zip_archive->close() === false ) throw new ZipException(self::getStatus($this->zip_archive->status));

        return true;

    }

    /**
     * Get a list of files in archive (array)
     *
     * @return  array
     */
    public function listFiles() {

        $list = Array();

        for ( $i = 0; $i < $this->zip_archive->numFiles; $i++ ) {

            $name = $this->zip_archive->getNameIndex($i);

            if ( $name === false ) throw new ZipException(self::getStatus($this->zip_archive->status));

            array_push($list, $name);

        }

        return $list;

    }

    /**
     * Extract files from zip archive
     *
     * @param   string  $destination    Destination path
     * @param   mixed   $files          (optional) a filename or an array of filenames
     *
     * @return  bool
     */
    public function extract($destination, $files=null) {

        if ( empty($destination) ) throw new ZipException('Invalid destination path');

        if ( !file_exists($destination) ) {

            $action = mkdir($destination, $this->mask, true);

            if ( $action === false ) throw new ZipException("Error creating folder ".$destination);

        }

        if ( !is_writable($destination) ) throw new ZipException('Destination path not writable');

        //$destination = substr($destination, -1) == '/' ? $destination : $destination.'/';

        if ( is_array($files) AND @sizeof($files) != 0 ) {

            $file_matrix = $files;

        }
        else $file_matrix = $this->getArchiveFiles();

        $extract = $this->zip_archive->extractTo($destination, $file_matrix);

        if ( $extract === false ) throw new ZipException(self::getStatus($this->zip_archive->status));

        return true;

    }

    /**
     * Add files to zip archive
     *
     * @param   mixed   $file_name_or_array     filename to add or an array of filenames
     *
     * @return  Object  $this
     */
    public function add($file_name_or_array) {

        if ( empty($file_name_or_array) ) throw new ZipException(self::getStatus(ZipArchive::ER_NOENT ));

        try {

            if ( is_array($file_name_or_array) ) {

                foreach ($file_name_or_array as $file_name) $this->addItem($file_name);

            }
            else $this->addItem($file_name_or_array);
            
        } catch (ZipException $ze) {
            
            throw $ze;

        }

        return $this;

    }

    /**
     * Delete files from zip archive
     *
     * @param   mixed   $file_name_or_array     filename to delete or an array of filenames
     *
     * @return  Object  $this
     */
    public function delete($file_name_or_array) {

        if ( empty($file_name_or_array) ) throw new ZipException(self::getStatus(ZipArchive::ER_NOENT ));

        try {

            if ( is_array($file_name_or_array) ) {

                foreach ($file_name_or_array as $file_name) $this->deleteItem($file_name);

            }
            else $this->deleteItem($file_name_or_array);
            
        } catch (ZipException $ze) {
            
            throw $ze;

        }

        return $this;

    }

    /**
     * Get a list of file contained in zip archive before extraction
     *
     * @return  Object  ZipArchive
     */
    private function getArchiveFiles() {

        $list = Array();

        for ($i = 0; $i < $this->zip_archive->numFiles; $i++) {

            $file = $this->zip_archive->statIndex($i);

            if ( $file === false ) continue;

            $name = str_replace('\\', '/', $file['name']);

            if ( $name[0] == "." AND in_array( $this->skip_mode, array("HIDDEN", "ALL") ) ) continue;

            if ( $name[0] == "." AND @$name[1] == "_" AND in_array( $this->skip_mode, array("COMODOJO", "ALL") ) ) continue;         

            array_push($list, $name);

        }

        return $list;

    }

    /**
     * Add item to zip archive
     *
     * @param   int $file   File to add (realpath)
     * @param   int $base   (optional) Base to record in zip file
     */
    private function addItem($file, $base=null) {

        $file = is_null($this->path) ? $file : $this->path . $file;

        $real_file = str_replace('\\', '/', realpath($file));

        $real_name = basename($real_file);

        if ( !is_null($base) ) {

            if ( $real_name[0] == "." AND in_array( $this->skip_mode, array("HIDDEN", "ALL") ) ) return;

            if ( $real_name[0] == "." AND @$real_name[1] == "_" AND in_array( $this->skip_mode, array("COMODOJO", "ALL") ) ) return;

        }

        if ( is_dir($real_file) ) {

            $folder_target = is_null($base) ? $real_name : $base.$real_name;

            $new_folder = $this->zip_archive->addEmptyDir($folder_target);

            if ( $new_folder === false ) throw new ZipException(self::getStatus($this->zip_archive->status));

            foreach(new \DirectoryIterator($real_file) as $path) {
    
                if ( $path->isDot() ) continue;

                $file_real = $path->getPathname();

                $base = $folder_target."/";

                try {
                    
                    $this->addItem($file_real, $base);

                } catch (ZipException $ze) {
                    
                    throw $ze;

                }

            }

        }
        else if ( is_file($real_file) ) {

            $file_target = is_null($base) ? $real_name : $base.$real_name;

            $add_file = $this->zip_archive->addFile($real_file, $file_target);

            if ( $add_file === false ) throw new ZipException(self::getStatus($this->zip_archive->status));

        }
        else return;

    }
    
    /**
     * Delete item from zip archive
     *
     * @param   int $file   File to delete (zippath)
     */
    private function deleteItem($file) {

        $deleted = $this->zip_archive->deleteName($file);

        if ( $deleted === false ) throw new ZipException(self::getStatus($this->zip_archive->status));

    }

    /**
     * Open a zip file
     *
     * @param   int $code   ZIP status code
     * @param   int $code   ZIP status code
     *
     * @return  Object  ZipArchive
     */
    static private function openZipFile($zip_file, $flags=null) {

        $zip = new ZipArchive();

        $open = $zip->open($zip_file, $flags);

        if ($open !== true) throw new ZipException(self::getStatus($open));
        
        return $zip;

    }

    /**
     * Get status from zip status code
     *
     * @param   int $code   ZIP status code
     *
     * @return  string
     */
    static private function getStatus($code) {

        if ( array_key_exists($code, self::$zip_status_codes) ) return self::$zip_status_codes[$code];

        else return sprintf('Unknown status %s', $code);

    }

}