<?php namespace Faddle\Helper;

/**
 * Read ID3Tags and thumbnails.
 *
 * @author Shubham Jain <shubham.jain.1@gmail.com>
 * @license MIT License
 */
class Id3TagsReader
{

    private $fileReader;
    private $id3Array;
    private $validMp3 = TRUE;

    public function __construct($fileHandle)
    {
        $this->fileReader = new BinaryFileReader($fileHandle, array(
            "id3" => array(BinaryFileReader::FIXED, 3),
            "version" => array(BinaryFileReader::FIXED, 2),
            "flag" => array(BinaryFileReader::FIXED, 1),
            "sizeTag" => array(BinaryFileReader::FIXED, 4, BinaryFileReader::INT),
        ));

        $data = $this->fileReader->read();

        if( $data->id3 !== "ID3")
        {
            throw new \Exception("The MP3 file contains no valid ID3 Tags.");
            $this->validMp3 = FALSE;
        }

    }

    public function readAllTags()
    {
        assert( $this->validMp3 === TRUE);

        $bytesPos = 10; //From headers

        $this->fileReader->setMap(array(
            "frameId" => array(BinaryFileReader::FIXED, 4),
            "size" => array(BinaryFileReader::FIXED, 4, BinaryFileReader::INT),
            "flag" => array(BinaryFileReader::FIXED, 2),
            "body" => array(BinaryFileReader::SIZE_OF, "size"),
        ));

        $id3Tags = Id3Tags::getId3Tags();

        while (($file_data = $this->fileReader->read())) {

            if (!in_array($file_data->frameId, array_keys($id3Tags))) {
                break;
            }

            $this->id3Array[$file_data->frameId] = array(
                "fullTagName" => $id3Tags[$file_data->frameId],
                "position" => $bytesPos,
                "size" => $file_data->size,
                "body" => $file_data->body,
            );

            $bytesPos += 4 + 4 + 2 + $file_data->size;
        }
        return $this;
    }

    public function getId3Array()
    {
        return $this->id3Array;
    }

    public function getImage()
    {
        $fp = fopen('data://text/plain;base64,' . base64_encode($this->id3Array["APIC"]["body"]), 'rb'); //Create an artificial stream from Image data

        $fileReader = new BinaryFileReader($fp, array(
            "textEncoding" => array(BinaryFileReader::FIXED, 1),
            "mimeType" => array(BinaryFileReader::NULL_TERMINATED),
            "fileName" => array(BinaryFileReader::NULL_TERMINATED),
            "contentDesc" => array(BinaryFileReader::NULL_TERMINATED),
            "binaryData" => array(BinaryFileReader::EOF_TERMINATED)
            )
        );

        $imageData = $fileReader->read();

        return array($imageData->mimeType, $imageData->binaryData);
    }
}


/**
 * A simple class to read variable byte length binary data.
 * This is basically is a better replacement for unpack() function
 * which creates a very large associative array.
 *
 * @author Shubham Jain <shubham.jain.1@gmail.com>
 * @example https://github.com/shubhamjain/PHP-ID3
 * @license MIT License
 */
class BinaryFileReader
{
    /**
     * size of block depends upon the variable defined in the next array element.
     */
    const SIZE_OF = 1;

    /**
     * Block is read until NULL is encountered.
     */
    const NULL_TERMINATED = 2;

    /**
     * Block is read until EOF  is encountered.
     */
    const EOF_TERMINATED = 3;

    /**
     * Block size is fixed.
     */
    const FIXED = 4;

    /**
     * Datatypes to transform the read block
     */
    const INT = 5;

    const FLOAT = 6;

    /**
     * file handle to read data
     */
    private $fp;

    /**
     * Associative array of Varaibles and their info ( TYPE, SIZE, DATA_TYPE)
     * In special cases it can be an array to handle different types of block data lengths
     */
    private $map;

    public function __construct($fp, array $map)
    {
        $this->fp = $fp;
        $this->setMap($map);
    }

    public function setMap($map)
    {
        $this->map = $map;

        foreach ($this->map as $key => $size) {
            //Create property from keys of $map
            $this->$key = null;
        }
    }

    public function read()
    {
        if (feof($this->fp)) {
            return false;
        }

        foreach ($this->map as $key => $info) {

            $this->fillTag($info, $key);

            if (isset($info[2])) {
                $this->convertBinToNumeric($info[2], $key);
            }
            $this->$key = ltrim($this->$key, "\0x");
        }
        return $this;
    }

    private function nullTeminated($key)
    {
        while ((int) bin2hex(($ch = fgetc($this->fp))) !== 0) {
            $this->$key .= $ch;
        }
    }

    private function eofTerminated($key)
    {
        while (!feof($this->fp)) {
            $this->$key .= fgetc($this->fp);
        }
    }

    private function fillTag($tag, $key)
    {
        switch ($tag[0]) {
            case self::NULL_TERMINATED:
                $this->nullTeminated($key);
                break;
            case self::EOF_TERMINATED:
                $this->eofTerminated($key);
                break;
            case self::SIZE_OF:
                //If the variable is not an integer return false
                if (!( $tag[1] = $this->$tag[1] )) {
                    return false;
                }
            default:
                //Read as string
                $this->$key = fread($this->fp, $tag[1]);
                break;
        }
    }

    private function convertBinToNumeric($value, $key)
    {
        switch ($value) {
            case self::INT:
                $this->$key = intval(bin2hex($this->$key), 16);
                break;
            case self::FLOAT:
                $this->$key = floatval(bin2hex($this->$key), 16);
                break;
        }
    }
}


class Id3Tags
{
    public static function getId3Tags()
    {
        return array(
            "AENC" => "Audio encryption",
            "APIC" => "Attached picture",
            "COMM" => "Comments",
            "COMR" => "Commercial frame",
            "ENCR" => "Encryption method registration",
            "EQUA" => "Equalization",
            "ETCO" => "Event timing codes",
            "GEOB" => "General encapsulated object",
            "GRID" => "Group identification registration",
            "IPLS" => "Involved people list",
            "LINK" => "Linked information",
            "MCDI" => "Music CD identifier",
            "MLLT" => "MPEG location lookup table",
            "OWNE" => "Ownership frame",
            "PRIV" => "Private frame",
            "PCNT" => "Play counter",
            "POPM" => "Popularimeter",
            "POSS" => "Position synchronisation frame",
            "RBUF" => "Recommended buffer size",
            "RVAD" => "Relative volume adjustment",
            "RVRB" => "Reverb",
            "SYLT" => "Synchronized lyric/text",
            "SYTC" => "Synchronized tempo codes",
            "TALB" => "Album/Movie/Show title",
            "TBPM" => "BPM (beats per minute)",
            "TCOM" => "Composer",
            "TCON" => "Content type",
            "TCOP" => "Copyright message",
            "TDAT" => "Date",
            "TDLY" => "Playlist delay",
            "TENC" => "Encoded by",
            "TEXT" => "Lyricist/Text writer",
            "TFLT" => "File type",
            "TIME" => "Time",
            "TIT1" => "Content group description",
            "TIT2" => "Title/songname/content description",
            "TIT3" => "Subtitle/Description refinement",
            "TKEY" => "Initial key",
            "TLAN" => "Language(s)",
            "TLEN" => "Length",
            "TMED" => "Media type",
            "TOAL" => "Original album/movie/show title",
            "TOFN" => "Original filename",
            "TOLY" => "Original lyricist(s)/text writer(s)",
            "TOPE" => "Original artist(s)/performer(s)",
            "TORY" => "Original release year",
            "TOWN" => "File owner/licensee",
            "TPE1" => "Lead performer(s)/Soloist(s)",
            "TPE2" => "Band/orchestra/accompaniment",
            "TPE3" => "Conductor/performer refinement",
            "TPE4" => "Interpreted, remixed, or otherwise modified by",
            "TPOS" => "Part of a set",
            "TPUB" => "Publisher",
            "TRCK" => "Track number/Position in set",
            "TRDA" => "Recording dates",
            "TRSN" => "Internet radio station name",
            "TRSO" => "Internet radio station owner",
            "TSIZ" => "Size",
            "TSRC" => "ISRC (international standard recording code)",
            "TSSE" => "Software/Hardware and settings used for encoding",
            "TYER" => "Year",
            "TXXX" => "User defined text information frame",
            "UFID" => "Unique file identifier",
            "USER" => "Terms of use",
            "USLT" => "Unsychronized lyric/text transcription",
            "WCOM" => "Commercial information",
            "WCOP" => "Copyright/Legal information",
            "WOAF" => "Official audio file webpage",
            "WOAR" => "Official artist/performer webpage",
            "WOAS" => "Official audio source webpage",
            "WORS" => "Official internet radio station homepage",
            "WPAY" => "Payment",
            "WPUB" => "Publishers official webpage",
            "WXXX" => "User defined URL link frame",
        );
    }
}
