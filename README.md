# Disclosure & Barring Service (DBS) Update Service
A PHP library for interacting with the DBS update service API.
*Please ★ this package if you found it useful :)*

## Installation
The recommended way to install this library is [through Composer](https://getcomposer.org).  
This will install the latest supported version:

```bash
$  composer require KiloSierraCharlie/DisclosureBarringService
```
## Configuration
When initiating the `UpdateServiceAPI` class you'll be required to pass the following details, which will be visible to the certificate holder:
|Variable         |Description                                     |Example          |
|-----------------|------------------------------------------------|-----------------|
|ORGANISATION_NAME|The name of your organisation.                  |ACME Inc.        |
|YOUR_FORENAME    |The forename of the person requesting the check.|John, Automated**|
|YOUR_SURNAME     |The surname of the person requesting the check. |Smith, Check**   |

Some organisations may wish to use generic details for forename and surname, particularly for automated checks - in this case, set the forename and surname as something recognisable and descriptive for the certificate holder.

## Example Use
You can use this package in a standalone fashion, or as part of a framework such as Symfony or Laravel. 
### Standalone Use
```php
<?php
use KiloSierraCharlie\DisclosureBarringService\UpdateServiceAPI;

$updateService = new UpdateServiceAPI( "ORGANISATION_NAME", "YOUR_FORENAME", "YOUR_SURNAME" );
$result = $updateService->getCertificateStatus( CERTIFICATE_NUMBER, "CERTIFICATE_SURNAME", DATE_OF_BIRTH );

$result->isCurrent(); #Boolean
$result->isClear(); #Boolean
```

### Symfony Use
Firstly you'll need to configure the service, the easiest way would be to use the AutoWire to pass environment variables. You could manually create an instance of `UpdateServiceAPI` in your class and pass these from a user object if required. 

**.env**:
```Dotenv
DBS_ORG_NAME=ACME Inc
DBS_FORENAME=Automated
DBS_SURNAME=Check
```
**services.yaml**:
```yaml
services:
    ...
    KiloSierraCharlie\DisclosureBarringService\UpdateServiceAPI:
        arguments:
            - '%env(DBS_ORG_NAME)%'
            - '%env(DBS_FORENAME)%'
            - '%env(DBS_SURNAME)%'
```

**In your controller/command etc**:
```php
use KiloSierraCharlie\DisclosureBarringService\UpdateServiceAPI;
...
#[Route('/check_dbs', name: 'check_dbs')]
public function check_dbs( UpdateServiceAPI $updateService, Request $request ){
    ...
    $result = $updateService->getCertificateStatus( $request->request->get("CERTIFICATE_ID"),
                                                    $request->request->get("CERTIFICATE_SURNAME"),
                                                    $request->request->get("DATE_OF_BIRTH")
                                         );
    return $this->json([
        'current' => $result->isCurrent(),
        'clear' => $result->isClear()
    ]);
}
```  

## API Terms and Conditions (T&Cs)
The following T&Cs are taken from the DBS API documentation from November 2018. It is possible that the T&Cs have since changed, it's advised that you check before using this package. 

>I confirm I have the authority of the individual to which this DBS Certificate number relates to receive up-to-date information (within the meaning of section 116A of the Police Act 1997) in relation to their criminal record DBS Certificate for the purposes of asking an exempted question within the meaning of section 113A of the Police Act 1997; or in relation to their enhanced criminal record DBS Certificate for the purposes of asking an exempted question for a prescribed purpose within the meaning of section 113B of the Police Act 1997.