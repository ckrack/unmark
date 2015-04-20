<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Import external nilai marks controller
 * @author kip9
 *
 */
class Import extends Plain_Controller
{

    public function __construct()
    {
        parent::__construct();
        parent::redirectIfLoggedOut();

        // If we can't find a user id, get them out of here
        if (! isset($this->user_id) || ! is_numeric($this->user_id)) {
            header('Location: /');
            exit;
        }

        // Set default success to false
        $this->data['success'] = false;

    }

    public function index()
    {
        if (empty($_FILES) || empty($_FILES['upload'])) {
            $this->data['success'] = false;
            $this->data['errors'] = formatErrors(100);
            $this->exceptional->createTrace(E_ERROR, 'No JSON file uploaded for import.', __FILE__, __LINE__);
            $this->view('import/index', array('no_header' => true, 'no_footer' => true));
            return;
        }

        $params = array('user_id' => $this->user_id);
        $uploadedFile = $_FILES['upload'];

        $this->load->library('DeliciousImport', $params);

        $validationResult = $this->deliciousimport->validateUpload($uploadedFile);
        if ($validationResult === true) {
            $importResult = $this->deliciousimport->importFile($uploadedFile['tmp_name']);
            $this->data = $importResult;
            $this->view('import/index', array('no_header' => true, 'no_footer' => true));
            return;
        }

        $this->load->library('PocketImport', $params);

        $validationResult = $this->pocketimport->validateUpload($uploadedFile);
        if ($validationResult === true) {
            $importResult = $this->pocketimport->importFile($uploadedFile['tmp_name']);
            $this->data = $importResult;
            $this->view('import/index', array('no_header' => true, 'no_footer' => true));
            return;
        }

        $this->load->library('NetscapeImport', $params);

        $validationResult = $this->netscapeimport->validateUpload($uploadedFile);
        if ($validationResult === true) {
            $importResult = $this->netscapeimport->importFile($uploadedFile['tmp_name']);
            $this->data = $importResult;
            $this->view('import/index', array('no_header' => true, 'no_footer' => true));
            return;
        }

        $this->data['errors'] = $validationResult;
        $data = array();
        foreach ($validationResult as $k => $v) {
            $data['validation_error_' . $k] = $v;
        }
        $this->exceptional->createTrace(E_ERROR, 'JSON Import Issue', __FILE__, __LINE__, $data);
        $this->view('import/index', array('no_header' => true, 'no_footer' => true));
    }

}
