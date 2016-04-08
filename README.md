# portabase-api
PortaBase API Client

# Usage
```php

// Create client
$client = new \AtaneNL\PortaBase\Client('yourhost.portabase.nl', 'yourApiKey');

// Fetch host parents information
$hosts = $client->getHosts();

// Fetch managers
$managers = $client->getManagers();

```
