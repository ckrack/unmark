<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Plain_Controller extends CI_Controller {

    public $clean          = null;
    public $csrf_valid     = false;
    public $db_clean       = null;
    public $flash_message  = array();
    public $footer         = 'footer';
    public $header         = 'header';
    public $html_clean     = null;
    public $is_api         = false;
    public $original       = null;

    public function __construct()
    {
        // Start session
        // Will switch this up once we have proper session handlers in place
        // For now using native PHP sessions & removing CI sessions
        session_start();

        // Call home
        parent::__construct();

        // Clean incoming variables in a variety of ways
        $this->clean();

        // Generate CSRF token per user
        $this->generateCSRF();

        // Get any flash messages
        $this->getFlashMessages();

        // Figure if request if from API path or not
        $this->isAPI();

    }

    // Clean any variables coming in from POST or GET 3 ways
    // We have the originals, clean and db_clean versions accessible
    protected function clean()
    {
        $method            = (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST') ? $_POST : $_GET;
        $this->original   = new stdClass;
        $this->clean      = new stdClass;
        $this->db_clean   = new stdClass;
        $this->html_clean = new stdClass;

        if (! empty($method)) {
            foreach ($method as $k => $v) {
                $this->original->{$k}   = $v;
                $v                      = trim(decodeValue($v));
                $this->clean->{$k}      = strip_tags($v);
                $this->db_clean->{$k}   = mysqli_real_escape_string($this->db->conn_id, $this->clean->{$k});
                $this->html_clean->{$k} = mysqli_real_escape_string($this->db->conn_id, purifyHTML($v));
            }
        }

    }

    // Check and generate CRSF tokens
    protected function generateCSRF()
    {
        if (isset($this->clean->csrf_token)) {
            if (isset($_SESSION['csrf_token']) && ! empty($_SESSION['csrf_token'])) {
                $this->csrf_valid = ($_SESSION['csrf_token'] == $this->clean->csrf_token) ? true : false;
            }

            if ($this->csrf_valid === false) {
                $this->setFlashMessage('We could not locate the correct security token. Please try again.');
            }
        }

        if (! isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = generateCSRF();
        }
    }

    protected function getFlashMessages()
    {
        if (isset($_SESSION['flash_message']['message']) && ! empty($_SESSION['flash_message']['message'])) {
            $this->flash_message['type']    = $_SESSION['flash_message']['type'];
            $this->flash_message['message'] = $_SESSION['flash_message']['message'];
            unset($_SESSION['flash_message']);
        }
    }

    protected function isAPI()
    {
        $segment      = $this->uri->segment(1);
        $this->is_api = (! empty($segment) && strtolower($segment) == 'api') ? true : false;
    }

    // If logged if invalid CSRF token is not valid
    protected function redirectIfInvalidCSRF()
    {
        if (empty($this->csrf_valid)) {
            header('Location: /');
            exit;
        }
    }

    // If logged in, redirect
    protected function redirectIfLoggedIn($url='/')
    {
        if (isset($_SESSION['logged_in'])) {
            header('Location: ' . $url);
            exit;
        }
    }

    // If logged out, redirect
    protected function redirectIfLoggedOut($url='/')
    {
        if (! isset($_SESSION['logged_in'])) {
            header('Location: ' . $url);
            exit;
        }
    }

    // If not an admin, redirect
    protected function redirectIfNotAdmin($url='/')
    {
        if (! isset($_SESSION['user']['admin']) || empty($_SESSION['user']['admin'])) {
            header('Location: ' . $url);
            exit;
        }
    }

    protected function render($arr)
    {
        $json         = json_encode($arr, JSON_FORCE_OBJECT);
        $callback     = (isset($this->clean->callback)) ? $this->clean->callback : null;
        $json         = (isset($this->clean->content_type) && strtolower($this->clean->content_type) == 'jsonp') ? $callback . '(' . $json . ');' : $json;
        $content_type = (isset($this->clean->content_type) && strtolower($this->clean->content_type) == 'jsonp') ? 'application/javascript' : 'application/json';

        $this->view('json/index', array(
            'json'         => $json,
            'content_type' => $content_type,
            'no_debug'     => true,
            'no_header'    => true,
            'no_footer'    => true
        ));
    }

    // Add user info to session
    protected function sessionAddUser($user)
    {
        $_SESSION['logged_in'] = true;
        $_SESSION['user']      = array();
        foreach ($user as $k => $v) {
            $_SESSION['user'][$k] = $v;
        }
    }

    // Clear all session variables and cookies
    protected function sessionClear()
    {
        // Remove phpsessid
        // Remove ci_session (legacy)
        // destroy session
        // set global back to empty array
        setcookie('PHPSESSID', '', time()-31557600, '/', $_SERVER['HTTP_HOST']);
        setcookie('ci_session', '', time()-31557600, '/', $_SERVER['HTTP_HOST']);
        session_destroy();
        $_SESSION = array();
    }

    protected function setFlashMessage($message, $type='error')
    {
        $_SESSION['flash_message']            = array();
        $_SESSION['flash_message']['type']    = $type;
        $_SESSION['flash_message']['message'] = $message;
    }

    // Process a view
    // This is used so that we can easily add partials to all views
    protected function view($view, $data=array())
    {
        $data['csrf_token']    = $_SESSION['csrf_token'];
        $data['flash_message'] = $this->flash_message;

        // Strip tags from page_title
        if (isset($data['page_title'])) {
            $data['page_title'] = strip_tags($data['page_title']);
        }

        //If there is a header file, load it
        $header = (isset($data['header'])) ? $data['header'] : $this->header;
        if (! isset($data['no_header']) && ! isset($data['json'])) {
            $this->load->view($header, $data);
        }

        //Load main view file
        $this->load->view($view, $data);


        //If there is a footer file, load it
        $footer = (isset($data['footer'])) ? $data['footer'] : $this->footer;
        if (! isset($data['no_footer']) && ! isset($data['json'])) {
            $this->load->view($footer, $data);
        }

        //If the template is asking to debug, load it
        /*if (isset($this->clean->debug) && ((isset($_SESSION['account']['admin']) && ! empty($_SESSION['account']['admin'])) || ENVIRONMENT != 'production')) {
            $data['page_data']      = $data;
            $this->load->view('partials/internal/debug', $data);
        }*/
    }

}