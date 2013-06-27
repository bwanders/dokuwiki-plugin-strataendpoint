Strata Endpoint
===============

This plugin depends on [bwanders/dokuwiki-strata](https://github.com/bwanders/dokuwiki-strata).

The Strata Endpoint plugin is used to offer the structured data in the wiki to external tools.

It offers the following syntax:
```
<endpoint>
type: resources
allow-origin {
  *
}
query {
  fields {
    ?s
    ?p
    ?o
  }
  ?s ?p: ?o
}
</endpoint>
```

The `type` field should be either `resources` or `relations`.

The `allow-origin` group handles [CORS](http://en.wikipedia.org/wiki/Cross-origin_resource_sharing), and each line should contain an allowed origin, or the only line should be `*`, to signify all origins are allowed.

The `query` group is optional, if left out, the POST data will be interpreted as a query.
