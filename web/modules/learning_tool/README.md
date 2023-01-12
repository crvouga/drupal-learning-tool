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

## Resource Launch Flow

- Step 1. Open Id Connect

## Deep Linking Launch Flow

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
