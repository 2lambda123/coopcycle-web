Feature: Stores

  Scenario: Not authorized to list stores
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/stores"
    Then the response status code should be 403

  Scenario: Not authorized to retrieve store
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/stores"
    Then the response status code should be 403

  Scenario: Not authorized to list store deliveries with JWT
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | deliveries.yml      |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_STORE"
    And the store with name "Acme2" belongs to user "bob"
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    When the user "bob" sends a "GET" request to "/api/stores/1/deliveries?order[dropoff.before]=asc"
    Then the response status code should be 403

  Scenario: Not authorized to list store deliveries with OAuth
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | deliveries.yml      |
    Given the store with name "Acme2" has an OAuth client named "Acme2"
    And the OAuth client with name "Acme2" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme2" sends a "GET" request to "/api/stores/1/deliveries?order[dropoff.before]=desc"
    Then the response status code should be 403

  Scenario: List my stores
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_STORE"
    And the store with name "Acme" belongs to user "bob"
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/me/stores"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Store",
        "@id":"/api/stores",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@id":"/api/stores/1",
            "@type":"http://schema.org/Store",
            "name":"Acme",
            "enabled":true,
            "address":@...@,
            "timeSlot":"/api/time_slots/1"
          }
        ],
        "hydra:totalItems":1
      }
      """

  Scenario: Retrieve store
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_STORE"
    And the store with name "Acme" belongs to user "bob"
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/stores/1"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Store",
        "@id":"/api/stores/1",
        "@type":"http://schema.org/Store",
        "name":"Acme",
        "enabled":true,
        "address":{
          "@id":"/api/addresses/1",
          "@type":"http://schema.org/Place",
          "geo":{
            "latitude":48.864577,
            "longitude":2.333338
          },
          "streetAddress":"272, rue Saint Honoré 75001 Paris 1er",
          "telephone":null,
          "name":null
        },
        "timeSlot":"/api/time_slots/1"
      }
      """

  Scenario: Retrieve time slot
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | stores.yml          |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the user "bob" sends a "GET" request to "/api/time_slots/1"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/TimeSlot",
        "@id":"/api/time_slots/1",
        "@type":"TimeSlot",
        "name":"Acme time slot",
        "choices":[
          {
            "startTime":"12:00:00",
            "endTime":"14:00:00"
          },
          {
            "startTime":"14:00:00",
            "endTime":"17:00:00"
          }
        ],
        "interval":"2 days",
        "workingDaysOnly":true
      }
      """

  Scenario: List store deliveries with JWT, ordered by dropoff desc
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | deliveries.yml      |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_STORE"
    And the store with name "Acme" belongs to user "bob"
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    When the user "bob" sends a "GET" request to "/api/stores/1/deliveries?order[dropoff.before]=desc"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Delivery",
        "@id":"/api/stores/1/deliveries",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@id":"/api/deliveries/2",
            "@type":"http://schema.org/ParcelDelivery",
            "id":2,
            "pickup":{
              "@id":"@string@.startsWith('/api/tasks')",
              "@type":"Task",
              "id":3,
              "address":@...@,
              "doneAfter":"@string@.isDateTime()",
              "after":"@string@.isDateTime()",
              "before":"2019-11-12T18:30:00+01:00",
              "doneBefore":"2019-11-12T18:30:00+01:00",
              "comments": ""
            },
            "dropoff":{
              "@id":"@string@.startsWith('/api/tasks')",
              "@type":"Task",
              "id":4,
              "address":@...@,
              "doneAfter":"@string@.isDateTime()",
              "after":"@string@.isDateTime()",
              "before":"2019-11-12T20:30:00+01:00",
              "doneBefore":"2019-11-12T20:30:00+01:00",
              "comments": ""
            }
          },
          {
            "@id":"/api/deliveries/1",
            "@type":"http://schema.org/ParcelDelivery",
            "id":1,
            "pickup":{
              "@id":"@string@.startsWith('/api/tasks')",
              "@type":"Task",
              "id":1,
              "address":@...@,
              "doneAfter":"@string@.isDateTime()",
              "after":"@string@.isDateTime()",
              "before":"2019-11-12T18:30:00+01:00",
              "doneBefore":"2019-11-12T18:30:00+01:00",
              "comments": ""
            },
            "dropoff":{
              "@id":"@string@.startsWith('/api/tasks')",
              "@type":"Task",
              "id":2,
              "address":@...@,
              "doneAfter":"@string@.isDateTime()",
              "after":"@string@.isDateTime()",
              "before":"2019-11-12T19:30:00+01:00",
              "doneBefore":"2019-11-12T19:30:00+01:00",
              "comments": ""
            }
          }
        ],
        "hydra:totalItems":2,
        "hydra:view":@...@,
        "hydra:search":@...@
      }
      """

  Scenario: List store deliveries with JWT, ordered by dropoff asc
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | deliveries.yml      |
    And the user "bob" is loaded:
      | email      | bob@coopcycle.org |
      | password   | 123456            |
    And the user "bob" has role "ROLE_STORE"
    And the store with name "Acme" belongs to user "bob"
    Given the user "bob" is authenticated
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    When the user "bob" sends a "GET" request to "/api/stores/1/deliveries?order[dropoff.before]=asc"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Delivery",
        "@id":"/api/stores/1/deliveries",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@id":"/api/deliveries/1",
            "@type":"http://schema.org/ParcelDelivery",
            "id":1,
            "pickup":{
              "@id":"@string@.startsWith('/api/tasks')",
              "@type":"Task",
              "id":1,
              "address":@...@,
              "doneAfter":"@string@.isDateTime()",
              "after":"@string@.isDateTime()",
              "before":"2019-11-12T18:30:00+01:00",
              "doneBefore":"2019-11-12T18:30:00+01:00",
              "comments": ""
            },
            "dropoff":{
              "@id":"@string@.startsWith('/api/tasks')",
              "@type":"Task",
              "id":2,
              "address":@...@,
              "doneAfter":"@string@.isDateTime()",
              "after":"@string@.isDateTime()",
              "before":"2019-11-12T19:30:00+01:00",
              "doneBefore":"2019-11-12T19:30:00+01:00",
              "comments": ""
            }
          },
          {
            "@id":"/api/deliveries/2",
            "@type":"http://schema.org/ParcelDelivery",
            "id":2,
            "pickup":{
              "@id":"@string@.startsWith('/api/tasks')",
              "@type":"Task",
              "id":3,
              "address":@...@,
              "doneAfter":"@string@.isDateTime()",
              "after":"@string@.isDateTime()",
              "before":"2019-11-12T18:30:00+01:00",
              "doneBefore":"2019-11-12T18:30:00+01:00",
              "comments": ""
            },
            "dropoff":{
              "@id":"@string@.startsWith('/api/tasks')",
              "@type":"Task",
              "id":4,
              "address":@...@,
              "doneAfter":"@string@.isDateTime()",
              "after":"@string@.isDateTime()",
              "before":"2019-11-12T20:30:00+01:00",
              "doneBefore":"2019-11-12T20:30:00+01:00",
              "comments": ""
            }
          }
        ],
        "hydra:totalItems":2,
        "hydra:view":@...@,
        "hydra:search":@...@
      }
      """

  Scenario: List store deliveries with OAuth, ordered by dropoff desc
    Given the fixtures files are loaded:
      | sylius_channels.yml |
      | deliveries.yml      |
    Given the store with name "Acme" has an OAuth client named "Acme"
    And the OAuth client with name "Acme" has an access token
    When I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And the OAuth client "Acme" sends a "GET" request to "/api/stores/1/deliveries?order[dropoff.before]=desc"
    Then the response status code should be 200
    And the response should be in JSON
    And the JSON should match:
      """
      {
        "@context":"/api/contexts/Delivery",
        "@id":"/api/stores/1/deliveries",
        "@type":"hydra:Collection",
        "hydra:member":[
          {
            "@id":"/api/deliveries/2",
            "@type":"http://schema.org/ParcelDelivery",
            "id":2,
            "pickup":{
              "@id":"@string@.startsWith('/api/tasks')",
              "@type":"Task",
              "id":3,
              "address":@...@,
              "doneAfter":"@string@.isDateTime()",
              "after":"@string@.isDateTime()",
              "before":"2019-11-12T18:30:00+01:00",
              "doneBefore":"2019-11-12T18:30:00+01:00",
              "comments": ""
            },
            "dropoff":{
              "@id":"@string@.startsWith('/api/tasks')",
              "@type":"Task",
              "id":4,
              "address":@...@,
              "doneAfter":"@string@.isDateTime()",
              "after":"@string@.isDateTime()",
              "before":"2019-11-12T20:30:00+01:00",
              "doneBefore":"2019-11-12T20:30:00+01:00",
              "comments": ""
            }
          },
          {
            "@id":"/api/deliveries/1",
            "@type":"http://schema.org/ParcelDelivery",
            "id":1,
            "pickup":{
              "@id":"@string@.startsWith('/api/tasks')",
              "@type":"Task",
              "id":1,
              "address":@...@,
              "doneAfter":"@string@.isDateTime()",
              "after":"@string@.isDateTime()",
              "before":"2019-11-12T18:30:00+01:00",
              "doneBefore":"2019-11-12T18:30:00+01:00",
              "comments": ""
            },
            "dropoff":{
              "@id":"@string@.startsWith('/api/tasks')",
              "@type":"Task",
              "id":2,
              "address":@...@,
              "doneAfter":"@string@.isDateTime()",
              "after":"@string@.isDateTime()",
              "before":"2019-11-12T19:30:00+01:00",
              "doneBefore":"2019-11-12T19:30:00+01:00",
              "comments": ""
            }
          }
        ],
        "hydra:totalItems":2,
        "hydra:view":@...@,
        "hydra:search":@...@
      }
      """
