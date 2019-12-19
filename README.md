# Aplia Content Filter

Advanced filter support for content objects in eZ publish legacy. The filter
support nested structures with `AND` and `OR` operations and more. It effectively
replaces other specific filter extensions such as `OWOrFilter`, as well as
regular attribute filters.

This installs as an extension called `apliafilter` and provides an
attribute filter called `NestedFilterSet`.


[![Latest Stable Version](https://img.shields.io/packagist/v/aplia/filter.svg?style=flat-square)](https://packagist.org/packages/aplia/filter)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%205.3-8892BF.svg?style=flat-square)](https://php.net/)

## Installation

Install this extension using composer:

```
composer require aplia/filter
```

## Usage

When listing content objects this filter can be enabled by installing it
as an extended attribute filter, then using `params` to define a nested
structure. The structure will then be transformed into SQL queries
and added to the normal content object query.

For instance in a template one can do:

```eztemplate
{fetch('content', 'list',
      hash(
        'parent_node_id', 2,
        'depth', 20,
        'limit', 10,
        'sort_by', array('published', false()),
        'class_filter_type', 'include',
        'class_filter_array', array('article'),
        'extended_attribute_filter', hash(
                'id', 'NestedFilterSet',
                'params', array(
                    array(array('article/type'), $type),
                )
            )
        )
    )}
```

A similar example in PHP:
```php
\eZContentObjectTreeNode::subTreeByNodeId(
    array(
        'Depth' => 20,
        'Limit' => 10,
        'SortBy' => ['published', false],
        'ClassFilterType' => 'include',
        'ClassFilterArray' => ['article'],
        'ExtendedAttributeFilter' => [
            'id' => 'NestedFilterSet',
            'params' => array(
                'cond' => 'OR',
                'attrs' => array(
                )
            ),
        ],
    ),
    2
);
```

## NestedFilterSet

An extended attribute filter which allows for nested structures and
unifies attribute filters and extended attribute filter.
The filter can be extended with custom classes which allows it to get around the
problem that only one extended attribute filter may be used.
The filter also supports filtering on multiple content classes at the same time,
it will do an outer join with the attribute table in this case.

The following elements can be extended:

1. Data-Types
2. Modifiers
3. Attributes

See filter.ini.append for configuration.

### Syntax

The syntax of each attribute filters are an array containing the attribute name
and the value, e.g.

```php
['name', 'foo']
```

The attribute name may also contain modifiers and an operator (default is `=`).
e.g.

```php
['my_class/size:>', 5]
```

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

```php
['cond' => 'and'/'or', 'attrs' => [...]
```

This defines a list of attribute filters with either an `and` or `or` condition between each filter.
A shorthand is available by just supplying a list of attribute filters, it will then default to `and` as the condition.

Entries in the `attrs` list is either defined as an array with `key` and `value`, like this:

```php
[
    'name' => 'foo',
    'folder/title' => 'bar',
]
```

or as an array with each entry being an array containg the attribute name and value, like this:
```php
[
    ['name', 'foo'],
    ['folder/title' => 'bar'],
]
```

The latter form allows for having the attribute name as an array of names, in which case
it will filter the value on all attributes with an `or` condition.

```php
[
    [['folder/title', 'article/title'] => 'bar'],
]
```

The long-form of this would be:

```php
[
    [
        'cond' => 'or',
        'attrs' => [
            'folder/title' => 'bar',
            'article/'title' => 'bar',
        ],
    ]
]
```

The attribute entry may also contain the array key `call`, which means
that a callback is used to fetch the filter structure. The callback may
either return a normal structure as extended attribute filters does
or return a new attribute filter structure, if the `cond` key is not
used it will assume the `and` condition and wrap it into a long-form
structure. The value for the `call` is any callback structure supported
by PHP.


A full example:
```php
[
    ['folder/is_public', true],
    [
        'cond' => 'or',
        'attrs' => [
            'folder/title' => 'bar',
            'article/'title' => 'bar',
        ],
    ],
    [
        'call' => ['MyClass', 'makeFilter'],
    ]
]
```

### Data-Types

These are handlers for the content class datatypes which allows for dynamic attribute names to be used.
The content class attribute is looked up and the first handler to support the data-type will
be used for creating joins and the filter.

The following specialized data-types are supported:

* ezobjectrelation
* ezobjectrelationlist
* ezselection

In addition it supports datatypes from the following extensions:

* eztags - Filters on the eztags keyword ids

The others falls back to a default handler which uses `sort_key_string` or `sort_key_int`.

Data-Type handlers are specified using the classname with namespace prefixed.

### Modifiers

Modifiers are simple callbacks which can wrap SQL functions around the fields or values.

Modifiers are specified in the attribute string, either before or after the operator.
`created:date` would wrap the `created` field in a `DATE()` SQL call while
`created:=:date` would wrap the matching value.

Modifiers may also be specified in the `array()` form of a filter as the fourth
parameter. e.g.

```php
['folder/name', '=', 'A', ['pre' => 'first_letter']]
```

Modifiers must be defined in `filter.ini` and point to a static method in a class,
the method will then receive one parameter which is name of the field and the
method must return a string with the SQL code which modifies the field.

e.g. to get the first letter one might do this:

First define the filter name and place the full namespace for the class +
`::` and the method name.

```ini
#filter.ini
[Handlers]
Modifiers[first_letter]=MyClass::firstLetter
```

Then define the code

```php
<?php
class MyClass
{
    public static firstLetter($field)
    {
        return "SUBSTRING($field, 1, 1)";
    }
}
```

### Attributes

Custom attributes may be added by defining the attribute name and a class
to use as the handler. The modifiers and operator are parsed before calling
the attribute handler.


## License

This library is open-sourced software licensed under the MIT license.
