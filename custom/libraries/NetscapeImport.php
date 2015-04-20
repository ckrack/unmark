<?php

if (! defined('BASEPATH'))
    exit('No direct script access allowed');

/**
 * NetscapeImport Class
 *
 * Library that handles Unmark data import from Netscape bookmarks file such as Chrome Bookmarks export
 *
 * @category Libraries
 */


class NetscapeImport
{

    /**
     * File type
     * @var string
     */
    const TYPE_HTML = 'text/html';


    /**
     * Doctype
     * @var string
     */
    const DOCTYPE_NETSCAPE = 'NETSCAPE-Bookmark-file';

    /**
     * Imported file handle
     * @var SplFileObject
     */
    private $tmpFile;

    /**
     * Parameters passed to json library
     * Has to contain user_id
     * @var array
     */
    private $params;

    /**
     * Creates JSON Importer library
     * Initializes CodeIgniter and saves passed params for later
     * @param array $params
     * @throws RuntimeException When no user_id is passed in params
     */
    public function __construct($params)
    {
        if (empty($params['user_id'])) {
            throw new RuntimeException('User_id was not passed for import. Cancelling');
        }
        $this->params = $params;
        $this->CI = & get_instance();
    }

    /**
     * Imports given file
     * @param string $filePath Path to a file with data to import
     * @return array Array with import output - metadata, status, warnings and errors
     */
    public function importFile($filePath)
    {
        $this->tmpFile->loadHTMLFile($filePath);
        $importData = array(
            'meta' => array(),
            'result' => array('added' => 0,
                              'skipped' => 0,
                              'failed' => 0,
                              'total' => 0),
            'user_id' => $this->params['user_id'],
            // TODO: this is hacky?
            'meta' => ['export_version' => 1]
        );
        $result = array('success' => false);

        $this->CI->load->library('Mark_Import', $importData);

        foreach ($this->tmpFile->getElementsByTagName('a') as $node) {
            $markObj = new stdClass;
            $markObj->title = substr($node->nodeValue, 0, 150);
            $markObj->url = $node->getAttribute("href");
            $markObj->created_on = date("Y-m-d H:i:s", $node->getAttribute("add_date"));
            $markObj->embed = null;
            $markObj->archived_on = null;
            $markObj->active = 1;

            $markObj->notes = $this->parseTags($node);
            $importResult = $this->CI->mark_import->importMark($markObj);

            if (isset($importResult) && is_array($importResult)) {
                // Returned array with results
                $importData['result']['total'] ++;
                $importData['result'][$importResult['result']] ++;
            }
        }
        return $importData;
    }

    protected function parseTags($node)
    {
        // no tag
        $XPath = new DOMXPath($node->ownerDocument);

        $arrTags = [];
        #foreach ($XPath->query('ancestor::dl', $node) as $tagNode) {
        foreach ($XPath->query('./ancestor::dl[ancestor::dl]', $node) as $tagNode) {
            if ($tagNode->previousSibling->firstChild->nodeValue !== 'Bookmarks' && $tagNode->previousSibling->firstChild->nodeName === 'h3') {
                $arrTags[] = mb_strtolower(trim($tagNode->previousSibling->nodeValue));
            }
        }
        $note = '';
        foreach ($arrTags as $key => $tag) {
            $arrTags[$key] = '#'.preg_replace("%([^\w]+)%", '-', $tag);
        }
        $note = implode(' ', $arrTags);

        return $note;
    }

    /**
     * Checks if passed file is valid
     * @param array $uploadedFile Uploaded file POST information
     * @return multitype:array|boolean True on success, array with error information otherwise
     */
    public function validateUpload($uploadedFile)
    {
        if (empty($uploadedFile) || $uploadedFile['size'] <= 0 || $uploadedFile['error'] != 0) {
            return formatErrors(100);
        }
        if ($uploadedFile['type'] !== self::TYPE_HTML) {
            return formatErrors(101);
        }
        // check for doctype..
        $this->tmpFile = new DOMDocument();
        $this->tmpFile->loadHTMLFile($uploadedFile['tmp_name']);

        if (!strstr($this->tmpFile->doctype->name, self::DOCTYPE_NETSCAPE)) {
            return formatErrors(101);
        }
        return true;
    }
}
