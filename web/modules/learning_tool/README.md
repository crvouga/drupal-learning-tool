[Download](https://www.drupal.org/project/drupal/releases/8.9.8) drupal inside of Applications > MAMP > htdocs
[Creating module tutorial](https://www.kalose.net/oss/drupal-8-create-simple-module/)
[how to get debugger working](https://joshbuchea.com/mac-enable-xdebug-in-mamp/)

To enable the debugger make this is in the search params

```
?XDEBUG_SESSION_START=xdebug
```

# LMS Setup

BASE_URL = http://localhost:8888/drupal-learning-tool/web/learning-tool
Login (aka OpenID) endpoint = /login
Key (aka JWKS) endpoint = /keys
Launch (aka Target Link) endpoint = /launch

# LTI 1.3 Flows

## Deep Linking Launch Flow

This flow is launched with the adding the tool as an activity inside of a course in the hosting LMS.
It "links" a "resource" to that activity so that resource can be access during the resource launch flow.

- Step 1. Open Id Connect

## Resource Launch Flow

This flow is launched when an activity is started inside of a course in the hosting LMS.
This launch is associated with a resource that was deep linked during the activities creation.

- Step 1. Open Id Connect

# Pitfalls

## Tools running inside Canvas must use HTTP

Solution: Using tunneling
https://github.com/localtunnel/localtunnel

```
npx localtunnel --port 8888 --subdomain drupal-learning-tool
```

output:

```
your url is: https://drupal-learning-tool.loca.lt
```

# Canvas Setup

## Local Setup

1.) https://github.com/instructure/canvas-lms/wiki/Quick-Start
