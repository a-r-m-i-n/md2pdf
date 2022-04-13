# armin/md2pdf

PHP application to convert multiple markdown files to HTML and PDF.

It's a more simple alternative to e.g. [pandoc](https://pandoc.org/).


**This is a very early prototype!** Without any release, yet.


## Documentation

Checkout the [full Documentation](docs/index.md) in this repository
or checkout the rendered PDF version (TODO).



## First steps

Download the latest phar binary or add this package (``armin/md2pdf``) to your composer.json.

When using Composer to require the package, you can access the binary on CLI using:
```
vendor/bin/md2pdf
```

Now, you need to create ``md2pdf.yaml`` file in your project's root directory.

**Minimum example:**
```yaml
title: Test Document
author: Armin Vieweg
baseUrl: https://github.com/a-r-m-i-n/md2pdf/blob/master
rootPath: .
structure:
  - section: Documentation
    level: 1
  - docs/index.md
  - docs/configuration.md
  - section: README Contents
    level: 1
  - README.md

output: md2pdf-documentation.pdf
```

In this file, we define the structure of the HTML/PDF output. We can also separate contents, using sections.

Now, you can simply run:

```
vendor/bin/md2pdf build
```


## Early version

As already mentioned before, this is a very early prototype. The code needs heavy refactoring, some functionality
is not working properly or still missing entirely.

It is planned to implement four modes you can choose from:
- init - to kickstart initial md2pdf.yaml file
- check - for new and missing files in structure
- update - update md2pdf.yaml structure (apply results from check)
- build - create the actual output

Currently, the build mode is the only implemented one.


## Features

- Merge configured markdown structure (files) and convert to HTML (using [league/commonmark](https://commonmark.thephpleague.com/2.0/extensions/overview/))
- Provide extended anchors (including sanitized filename)
- Replace relative links to documentation by extended anchors (*does not work properly, yet*)
- Prepend configured baseUrl to relative links to files in repository (which are not part of the configured markdwn structure)
- Provide table of content for every headline (with page numbers, good for printing)
- Provide bookmarks for every headline (good for PDF readers)
- Add custom sections to structure to separate contents
  - Level customizable (starts with 1)
  - You can also pass additional formatted texts, using the ``contents`` sub-key, which accepts markdown formatted input
- Syntax highlighting for embedded code (configurable, using [scrivo/highlight.php](https://github.com/scrivo/highlight.php))
- Configurable styles
- Manual page-breaks and orientation changes possible (```<!-- PAGEBREAK --> <!-- PAGEBREAK:L --> <!-- PAGEBREAK:P -->```)
- Output converted markdown contents (via HTML) to PDF (using [mpdf/mpdf](https://mpdf.github.io/))
- Phar compiler
  - because of filesize reasons, only the TTF fonts "DejaVuSerif" and "DejaVuSans" are included to phar binary


### Missing

- Modes: init, check and update
- Refactored analyzing of relative links in markdown content
- PDF setup
  - sizes, borders, etc
  - path to custom fonts
- ability to also save the HTML file
- configuration to disable bookmarks
- cleanup (remove tmp dir)
- complete documentation
