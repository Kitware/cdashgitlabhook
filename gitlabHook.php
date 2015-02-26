<?php
// To test this, turn off firewall on windows for http
// put https://github.com/m4tthumphrey/php-gitlab-api
// in CDash/plugins/gitlab
// put Buzz https://github.com/kriswallsmith/Buzz lib/Buzz
// in CDash/plugins/gitlab

// setup CDash path for CDash includes
$cdashpath = str_replace('\\', '/', dirname(dirname(dirname(__FILE__))));
set_include_path($cdashpath . PATH_SEPARATOR . get_include_path());


require_once('cdash/config.php');
require_once('cdash/common.php');
require_once('cdash/pdo.php');
require_once("cdash/log.php");

// define an autoload function to load Buzz and Gitlab files
// as they are requested with use directive
spl_autoload_register(function ($class) {
  $class = str_replace("\\", "/", $class);
  if(strpos($class, "Buzz") !== false)
    {
    include './Buzz/lib/' . $class . '.php';
    }
  else if(strpos($class, "Gitlab") !== false)
    {
    include './php-gitlab-api/lib/' . $class . '.php';
    }
  });
# use the Gitlab Client
use Gitlab\Client;


class GitlabHook
{
  private $cdashConfig;  // config information for CDash
  private $gitlabConfig; // config information for gitlab
  private $projectsConfig; // config information for projects
  private $client;       // gitlab client object
  private $cdashURL;     // url for cdash
  public function __construct($configfile)
  {
    // read the config file
    $json = file_get_contents($configfile);
    add_log("raw config settings:$json\n", "GitlabHook::__construct", LOG_INFO);
    $data = json_decode($json, TRUE);
    if($data == NULL)
      {
      $this->LogJsonParseError("__construct GitlabHook");
      }
    $json_string = print_r($data, true);
    add_log("parsed config settings:$json_string\n",
            "GitlabHook::__construct", LOG_INFO);
    $this->cdashConfig =  $data['cdash'];
    $this->gitlabConfig = $data['gitlab'];
    $this->projectsConfig = $data['projects'];
    // get the cdash url
    // get the current path to this file
    $currentDir = dirname(__FILE__);
    // assume CDash is 3 dirs up
    $cdashBaseDir = dirname(dirname(dirname(__FILE__)));
    // replace upto the start of our current dir with nothing
    $cdashCurrentDir = str_replace("$cdashBaseDir", "", $currentDir);
    // make sure the path is unix style for url
    $cdashCurrentDir = str_replace("\\", "/", $cdashCurrentDir);
    // now get the server url and strip out the current dir
    $this->cdashURL = get_server_URI(false);
    $this->cdashURL = str_replace("$cdashCurrentDir", "", $this->cdashURL);
  }
  public function LogJsonParseError($function)
  {
    $msg = "";
    switch (json_last_error()) {
        case JSON_ERROR_NONE:
          $msg =  ' - No errors';
        break;
        case JSON_ERROR_DEPTH:
          $msg = ' - Maximum stack depth exceeded';
        break;
        case JSON_ERROR_STATE_MISMATCH:
          $msg = ' - Underflow or the modes mismatch';
        break;
        case JSON_ERROR_CTRL_CHAR:
          $msg = ' - Unexpected control character found';
        break;
        case JSON_ERROR_SYNTAX:
          $msg = ' - Syntax error, malformed JSON';
        break;
        case JSON_ERROR_UTF8:
          $msg = ' - Malformed UTF-8 characters, possibly incorrectly encoded';
        break;
        default:
          $msg = ' - Unknown error';
        break;
    }
    add_log("Erorr parsing json: ${msg}", $function, LOG_ERR);
  }
  public function PrintConfig()
  {
    echo "CDash config settings:<br>";
    $config = print_r($this->cdashConfig, true);
    echo $config . "<br>";
    echo "CDash config settings:<br>";
    $config = print_r($this->gitlabConfig, true);
    echo $config . "<br>\n";
    echo "Project settings:<br>";
    $config = print_r($this->projectsConfig, true);
    echo $config . "<br>";
  }
  // This is the main entry point, this expects a json file to be sent to the script
  public function HandleRequest()
  {
    $request_string = file_get_contents('php://input');
    add_log("request json:[$request_string]\n", "GitlabHook::HandleRequest", LOG_INFO);
    $request = json_decode($request_string, true);
    if($request == NULL)
      {
      $this->LogJsonParseError("GitlabHook::HandleRequest");
      $this->PrintConfig();
      return;
      }
    $request_string = print_r($request, true);
    add_log("request json array:[$request_string]\n",
            "GitlabHook::HandleRequest", LOG_INFO);
    if($request['object_kind'] !== 'merge_request')
      {
      add_log("Gitlab Request not a merge_request was: "
              . $request['object_kind'], "GitlabHook::HandleRequest", LOG_INFO);
      return;
      }
    $merge_request = $request['object_attributes'];
    $source = $merge_request['source'];
    $http_url = $source['http_url'];
    $key_for_url = $this->gitlabConfig[$http_url];
    // create the gitlab client object
    try {
      $this->client = new \Gitlab\Client($this->gitlabConfig['url']);
      $this->client->authenticate($key_for_url,
                                  \Gitlab\Client::AUTH_URL_TOKEN);
    } catch (Exception $e) {
      add_log("Gitlab client exception " . $e->getMessage(),
              "GitlabHook::HandleRequest", LOG_ERR);
      add_log("Gitlab url " . $this->gitlabConfig['url'] .
              " key [$key_for_url]",
              "GitlabHook::HandleRequest", LOG_ERR);
    }
    // get the project id from gitlab using api
    try {
      $project = $this->client->api('projects')->show($merge_request['source_project_id']);
    } catch (Exception $e) {
      add_log("Gitlab client exception " . $e->getMessage(),
              "GitlabHook::HandleRequest", LOG_ERR);
    }
    $project_string = print_r($project, true);
    add_log("project array from Gitlab api ". "$project_string",
                "GitlabHook::HandleRequest",
                LOG_INFO);
    $target_project_id = $merge_request['target_project_id'];
    $repo_url = $project['http_url_to_repo'];
    $project_name = $project['name'];
    $merge_request_id = $merge_request['id'];
    $branch = $merge_request['source_branch'];
    $state = $merge_request['state'];
    $merge_status = $merge_request['merge_status'];
    if (in_array( 'merge_status', $merge_request))
      {
      $timestamp =  $merge_request['updated_at'];
      }
    else
      {
      $timestamp = $merge_request['created_at'];
      }
    $timestamp = date_parse_from_format ( 'Y-m-d H:i:s eq' , $timestamp );
    $time = mktime(
      $timestamp['hour'],
      $timestamp['minute'],
      $timestamp['second'],
      $timestamp['month'],
      $timestamp['day'],
      $timestamp['year']);
    if(array_key_exists($project_name, $this->projectsConfig))
      {
      if(($state !== 'closed') && ($merge_status === 'unchecked'))
        {
        $cdashInfo = $this->projectsConfig[$project_name];
        $build_tag = $this->CDashSubmitBuild($cdashInfo, $merge_request_id, $repo_url, $branch, $time);
        $this->AddMergeRequestComment($cdashInfo, $target_project_id, $merge_request_id,
                                      $build_tag, $project_name);
        }
      else
        {
        add_log("Gitlab merge_request not in state we handle".
                " state $state merge_status = $merge_status",
                "GitlabHook::HandleRequest",
                LOG_INFO);
        }
      }
    else
      {
      add_log("project_name [$project_name] not found in gitlab.config projects",
              "GitlabHook::HandleRequest",
              LOG_INFO);
      }
  }
  public function PostURL($url, $fields)
  {
    //url-ify the data for the POST
    $fields_string = "";
    foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
    $fields_string = rtrim($fields_string, '&');
    //open connection
    $ch = curl_init();
    $url = "$url?$fields_string";
    //set the url, number of POST vars, POST data
    curl_setopt($ch,CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    //execute post
    $result = curl_exec($ch);
    $result = json_decode($result, TRUE);
    if($result == NULL)
      {
      $this->LogJsonParseError("GitlabHook::PostURL");
      }
    //close connection
    curl_close($ch);
    return $result;
  }
  public function CDashSubmitBuild($cdash_info, $merge_request_id, $repo_url, $branch, $timestamp)
  {
    $build_tag = sprintf("%s-%s", $timestamp, $branch);
    $cdash_project_name = $cdash_info['cdash_project'];
    add_log("CDashSubmitBuild  $cdash_project_name\n",
            "GitlabHook::CDashSubmitBuild", LOG_INFO);
    $row = pdo_single_row_query("SELECT webapikey FROM project WHERE name='$cdash_project_name'");
    $key = $row['webapikey'];
    $email = $this->cdashConfig['user_email'];
    $row = pdo_single_row_query("SELECT id FROM user WHERE email='$email'");
    $cdash_user_id = $row['id'];
    $fields = array(
      'method' => urlencode('project'),
      'task' => urlencode('login'),
      'project' => urlencode("$cdash_project_name"),
      'key' => urlencode($key)
      );
    $url = sprintf('%s/api/', $this->cdashURL);
    $result = $this->PostURL($url, $fields);
    if(!array_key_exists('token', $result))
      {
      add_log("CDash login failed " . "project = $cdash_project_name key = $key\n",
              "GitlabHook::CDashSubmitBuild", LOG_ERR);
      return;
      }
    $token = $result['token'];
    $build_params = array(
      'method' => urlencode('build'),
      'task' => urlencode('schedule'),
      'project' => urlencode("$cdash_project_name"),
      'module' => urlencode('GitLab'),
      'tag' => urlencode($build_tag),
      'repository' => urlencode($repo_url),
      'userid' => urlencode($cdash_user_id),
      'token' => urlencode($token)
      );
    $platforms = $cdash_info['platforms'];
    foreach($platforms as $platform)
      {
      $build_params['osname'] = $platform;
      // post once for each platform
      $result = $this->PostURL($url, $build_params);
      }
    return $build_tag;
  }
  public function AddMergeRequestComment($cdash_info, $target_project_id, $merge_request_id,
                                         $build_tag, $project_name)
  {
    $cdash_project_name = $cdash_info['cdash_project'];
    $now = getdate();
    $filter_url = sprintf('%s/index.php?project=%s&filtercount=2&field1=buildname/string&compare1=63&value1=%s' .
                          '&field2=buildstarttime/date&compare2=83&value2=%s-%s-%s',
                          $this->cdashURL,
                          $cdash_project_name,
                          $build_tag,
                          $now['year'],
                          $now['mon'],
                          $now['mday']);
    $comment = 'Build submitted - See CDash results: [' . $filter_url . ']';
    try {
      $this->client->api('merge_requests')->addComment($target_project_id,
                                                     $merge_request_id,
                                                     $comment);
    } catch (Exception $e) {
      add_log("Gitlab client exception " . $e->getMessage(),
              "GitlabHook::AddMergeRequestComment", LOG_ERR);
    }
  }
}



// Run the main code here
// load the gitlab config file from this directory
$configfile = dirname(__FILE__).'/gitlab.config';
$gitlabHook = new GitlabHook($configfile);
// handle the request
$gitlabHook->HandleRequest();
?>
