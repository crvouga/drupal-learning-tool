[Download](https://www.drupal.org/project/drupal/releases/8.9.8) drupal inside of Applications > MAMP > htdocs
[Creating module tutorial](https://www.kalose.net/oss/drupal-8-create-simple-module/)
[how to get debugger working](https://joshbuchea.com/mac-enable-xdebug-in-mamp/)

To enable the debugger make this is in the search params

```
?XDEBUG_SESSION_START=xdebug
```

# LMS Setup

BASE_URL = http://localhost:8888/drupal-learning-tool/web/learning-tool
Login (aka OpenID) endpoint = /open-id-connect
Key (aka JWKS) endpoint = /jwks
Launch (aka Target Link) endpoint = /launch

# LTI 1.3 Flows

## Deep Linking Launch Flow

This flow is launched when adding the tool to a course activity.
It "links" a "resource" to that activity so that resource can be access during the resource launch flow.

For example: when a teacher is adding an activity to a course the

- Step 1. Open Id Connect

## Resource Launch Flow

This flow is launched when an activity is started inside of a course in the hosting LMS.
This launch is associated with a resource that was deep linked during the activities creation.

- Step 1. Open Id Connect
  The hosting LMS makes a request to an endpoint in the tool specified in the LMS. The tool

# Pitfalls

## Pitfall: LTI Tools running inside of Canvas must use HTTPS

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
