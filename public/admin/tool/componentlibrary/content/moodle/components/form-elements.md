---
layout: docs
title: "Form elements"
description: "Custom form elements for Moodle"
date: 2020-01-14T16:32:24+01:00
draft: false
weight: 30
tags:
- In development
---

## How to use moodle forms

Forms are constructed using lib/formslib.php. Using the addElement methods in php a set of different form element types can be added to a form. For more info visit the [Moodledocs](https://moodledev.io/docs/apis/subsystems/form) page for forms

{{< php >}}
    $mform->addElement('button', 'intro', get_string("buttonlabel"));
{{< / php >}}

Form elements are styled using Moodle templates which are rendered through PHP. Themes can override these templates.

Form elements are styled using the .form-control class, see the [Bootstrap](/bootstrap-4.3/forms/input-group/) docs for more info.

## Example form elements

The example form below is showing an iframe with an actual Moodle form. Use the toggle options to display the different states for the form.


<iframe src="../../../../examples/formfields.php" style="overflow:hidden;height:4000px;width:100%;border:0" title="Moodle form fields"></iframe>

## Editor attachments

The **Editor** field above accepts up to three draft attachments. With **Plain text area**
selected under **Preferences > Editor preferences**, it displays a **Manage files** link.
The link opens the standard file manager in a separate window while retaining unsaved
text in the original form. TinyMCE supplies its own manager and does not show this link.
The **Editor without file management** field demonstrates disabling the fallback.

{{< php >}}
$editoroptions = ['context' => $context, 'maxfiles' => 3];
$mform->addElement('editor', 'content', get_string('content'), null, $editoroptions);
$mform->setType('content', PARAM_RAW);
{{< / php >}}

Use the normal editor draft preparation and save functions in the owning form. File
operations affect the current user's draft area; closing the manager does not save the
owning form or insert file references into the text. This example form demonstrates the
controls only and does not persist content.

Set the editor option `enable_filemanagement` to `false` to suppress the fallback.
An editor that provides its own manager should override
`texteditor::provides_file_manager()` to return `true`.

Check that the link can be reached and opened using the keyboard, that returning to the
original window retains the text, and that the controls remain usable in the active theme.
