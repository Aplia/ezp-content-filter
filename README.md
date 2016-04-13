# Aplia Content Filter

Extension which contains filters for content objects in eZ publish.

## NestedFilterSet

An extended attribute filter which allows for nested structures and
unifies attribute filters and extended attribute filter.
The filter can be extended with custom classes which allows it to get around the
problem that only one extended attribute filter may be used.

Tthe following elements can be extended:

1. Data-Types
2. Modifiers
3. Attributes

See filter.ini.append for configuration.

### Syntax

The syntax of each attribute filters are an array containing the attribute name
and the value, e.g. ```['name', 'foo']```.
The attribute name may also contain modifiers and an operator (default is `=`).
e.g. ```['my_class/size:>', 5]```

The operators supported are the same as for the attribute filters in eZ publish.

- `=`
- `!=`
- `>`
- `>=`
- `<`
- `<=`
- `in`
- `not_in`
- `like`
- `not_like`
- `between`
- `not_between`

The parameters for the filter can be:

```['cond' => 'and'/'or', 'attrs' => [...]```

This defines a list of attribute filters with either an `and` or `or` condition between each filter.
A shorthand is available by just supplying a list of attribute filters, it will then default to `and` as the condition.

Entries in the `attrs` list is either defined as an array with `key` and `value`, like this:

```
[
    'name' => 'foo',
    'folder/title' => 'bar',
]
```

or as an array with each entry being an array containg the attribute name and value, like this:
```
[
    ['name', 'foo'],
    ['folder/title' => 'bar'],
]

The latter form allows for having the attribute name as an array of names, in which case
it will filter the value on all attributes with an `or` condition.

```
[
    [['folder/title', 'article/title'] => 'bar'],
]
```

The long-form of this would be:

```
[
    [
        'cond' => 'or',
        'attrs' => [
            'folder/title' => 'bar',
            'article/'title' => 'bar',
        ],
    ]
]

The attribute entry may also contain the array key `call`, which means
that a callback is used to fetch the filter structure. The callback may
either return a normal structure as extended attribute filters does
or return a new attribute filter structure, if the `cond` key is not
used it will assume the `and` condition and wrap it into a long-form
structure.


### Data-Types

These are handlers for the content class datatypes which allows for dynamic attribute names to be used.
The content class attribute is looked up and the first handler to support the data-type will
be used for creating joins and the filter.
The default implementation supports relation types (ezcontentrelation and ezcontentrelationlist)
as well as fallbacks for any data-type by using sort_key_string or sort_key_int.

Data-Type handlers are specified using the classname with namespace prefixed.

### Modifiers

Modifiers are simple callbacks which can wrap SQL functions around the fields or values.
The callback is specified as a static function call, with the class name + :: + function name.

Modifiers are specified in the attribute string, either before or after the operator.
`created:date` would wrap the `created` field in a `DATE()` SQL call while
`created:=:date` would wrap the matching value.

### Attributes

Custom attributes may be added by defining the attribute name and a class
to use as the handler. The modifiers and operator are parsed before calling
the attribute handler.
