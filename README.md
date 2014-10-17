ISPConfig-Mail-Management
=========================

ISPConfig simple mail management panel

Interface management as simple as possible.


# Installation

Set all variables

```
define("SALT", ""); // Random string must be unique (eg s7fd8CB5s2qq6)
define("IMAP", "localhost"); // IMAP Server IP
define("ADMIN_EMAIL", "admin@domain.tld"); // Email administrator for API errors
define("LOGFILE", dirname( __FILE__) . DIRECTORY_SEPARATOR . "log.log"); // LOGFILE (required write permission by current web user)
define("SOAP_LOCATION", "https://remote/index.php"); // ISPConfig API URL
define("SOAP_URI", "https://remote/"); // ISPConfig API URI
define("API_LOGIN", ""); // ISPConfig API Login
define("API_PASSWORD", ""); // ISPConfig API Password
define("LOGGER", TRUE); // Enable logger (TRUE/FALSE)
```

## Requirements

ISPConfig API access (need to clarify required permissions)


## TODO

Allow the changes on the current domain only (important)
