# Changelog

## 2.1.0

- Fixes to `ezselection` filter. Unset values should check for `NULL` in database.

## 2.1.0

- Added support for transferring extra array data in columns returned by datatypes
  to the created column array. These values are available in the column index `extra`.
- Join arrays may have `comment` index which will be embedded inline in the SQL
  for the join.
- Improved support for unset attributes in SelectionType, a value of 0 will also
  include unset attributes.
- Support for filtering only unset attributes in SelectionType.

## 2.0.0

- Changed namespace from ApliaContentFilter to Aplia\Content\Filter
