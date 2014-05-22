SuperModel
==========

Enhancement of the already brilliant Eloquent Model Class.

Features
========

- Auto validation - with seperate scopes (global/create/update)
- Custom validation messages based on scope (global/create/update)
- Schema Definition for type casting, password hashing, and array/object access of data
- Passwords are hashed when setting value, but validation is done on none hashed value
- Model values can be saved as arrays/objects via serialization
- Addition of ->validate() function for validating changes before save (called on save anyway)
- Addition of ->errors() function which contains the validation errors messagebag
- Auto Parsing of unique rules, simply provide the unique validation as normal and the table,field,id will be added for you (if not provided), or for custom queries with where clauses build the rule with the placeholder {id} and this will be replaced during validation

Usage
=====

Coming Soon.