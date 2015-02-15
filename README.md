To install:

put this in CDash/plugins/gitlab
clone:
https://github.com/kriswallsmith/Buzz

https://github.com/m4tthumphrey/php-gitlab-api.git

into this directory.

checkout this tag 6.9.1

Create a gitlab.config file like this:

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
