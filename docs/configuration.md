# Configuration

## CLI

When calling the md2pdf binary on CLI, you can pass some arguments and options,
which are described in detail.

The [argument "mode"](#argument-mode) is required.


### Argument: Mode

The md2pdf application only got one argument, which is the mode.
The following modes are available:

- ``init`` Kickstart a new *md2pdf.yaml* config file
- ``check`` Check for missing references and new pages, missing in structure (config)
- ``update`` Appends missing files to existing structure (config)
- ``build`` Builds the actual HTML/PDF file


## md2pdf.yaml file

Example:

```yaml
title: Test Document
author: Armin Vieweg
baseUrl: https://github.com/a-r-m-i-n/md2pdf/blob/master
structure:
  - section: README Contents
    level: 1
  - README.md
  - section: Dokumentation
    level: 1
  - docs/index.md
  - docs/configuration.md

output: test.pdf
```
