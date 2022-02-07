# Introduction

This is a plugin for CDash (www.cdash.org) that allows the build management
feature of CDash http://www.vtk.org/Wiki/CDash:Build_Management to be used
to automatically test gitlab https://about.gitlab.com/ merge requests. When
a merge request happens, gitlab sends a json file to this plugin. The plugin
then initiates a CDash build, and updates the merge request comment with a
link to the CDash results.

# CDashGitlabHook Installation

First you must install the plugin into your CDash installation. The
installation process involves cloning several git repostories into
the plubins directory of CDash and creating a gitlab.config json file.

Clone the CDashGitlabHook repository into CDash/plugins/gitlab
    cd CDash/plugins
    git clone https://github.com/Kitware/cdashgitlabhook.git gitlab

Clone Buzz into CDash/plugins/gitlab.
    cd CDash/plugins/gitlab
    git clone https://github.com/kriswallsmith/Buzz

Clone php-gitlab-api into CDash/plugins/gitlab
    cd CDash/plugins/gitlab
    git clone https://github.com/m4tthumphrey/php-gitlab-api.git
    git checkout 6.9.1

Create a gitlab.config file in CDash/plugins/gitlab/gitlab.config. Here is an
example config file.

    {
    "gitlab": {
        "url": "https://kwgitlab.kitwarein.com/api/v3/",
        "https://kwgitlab.kitwarein.com/hoffman/testcdash.git":
        "keyfromgitlab"
        },
    "cdash": {
        "user_email": "bill.hoffman@kitware.com"
    },
    "projects": {
        "testcdash": {
            "cdash_project": "TestProject",
            "platforms": [
                "Linux",
                "Windows"
            ]
        },
        "gitlabproj2": {
            "cdash_project": "cdashproj2",
            "platforms": [
                "Linux"
            ]
        }
      }
    }

# Register CDash webhook with Gitlab Installation

You must register the webhook for gitlab cdash integration with your Gitllab
instance.  The following steps are used to do that:

* Navigate to the project settings ( Project page, click "Edit" )
  Select "Web Hooks" on the left menu.

* Add the following URL to the "URL" field http://cdashserver/plugins/gitlab/gitlabHook.php

* Check "Merge Request events".

* Click "Add Web Hook"

* You can now test the connect to the webhook service. By clicking on "Test Hook".  This test will not trigger a CDash@home build but will send a test json file to the webhook.

* Test a merge request:

  * Goto the Commits tab on the Project page on gitlab, then click on Branches and create a new branch
  * Edit a file on that branch
  * Goto Merge Requests tab -> click New Merge Request
