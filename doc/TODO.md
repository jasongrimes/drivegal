Photo Gallery for Google Drive TODO list
========================================

Make a personal service:

* [x] Set up a LAMP environment (actually just need the LAP part for now)
* [x] Put a Silex skeleton together
* [x] Register drivegal.com
* [x] Configure apache to use the (www.)drivegal.com domain 
* [x] Get Google drive API fetch working
    - [x] Working prototype
    - [x] Refactor into something maintainable. (Stop including email in auth url, ...)
* [x] Make a web page for browsing folders and image lists 
* [x] Make a lightbox work with the image list
* [x] Tweak the UI to look how I want it
    - [x] Try another gallery, maybe https://github.com/blueimp/Gallery
    - [x] Make videos work
    - [x] Make thumbnails look good
    - [x] Make page title work
    - [x] Make albums look good.
    - [x] Show description
    - [x] Fetch 1000 results
    - [x] Sort photos by time created, albums by name.
    - [x] Make home page and layout look nice
    - [-] Use bootstrap breadcrumbs component
    - [x] Layer an arrow over video thumbnails?
    - [x] Clean up the code
* [x] Fix twig's inability to load error pages (causing fatal error when attempting to return a 404 etc.)
* [x] Fix bug creating a new gallery
* [x] Prevent creating galleries with duplicate names.
* [x] Import project to Github
* [x] Create the homepage
    - [x] Fix the title
    - [x] Maybe a nice parallax landing page?
    - [x] Mobile homepage
* [x] Improve the setup page
* [x] Add a footer
* [x] Give helpful messages when needed
    - [x] When clicking "cancel" on the oauth page.
    - [x] When viewing a gallery with revoked permissions
    - [x] When a gallery has no albums
    - [x] When an album has no photos
    - [x] Album 404 should redirect to main gallery page
* [ ] Add code comments
* [ ] Add login support (with Google oauth)
* [ ] Allow to manage an existing gallery.
* [ ] Add DB support
* [ ] Add an about page
* [ ] Tweak behavior to fit our photo workflow
* [ ] Be smarter about the title. Consider that a good workflow is to name the file for the title. Hide the file extension if it's at the end of the title. If the title is `IMG_*.{extension}`, hide it altogether. Use the date for the caption if there's no title or description.
* [ ] Use it
* [ ] Set up a reserved EC2 instance to keep costs down.
* [ ] Figure out how to close/slide after viewing a video on mobile. (Need to leave some free space around a border for the touch gestures?)

Make a public service:

* Add a copyright notice and privacy policy
* Release it and promote it
* Write articles about photo workflow and portability

Other possible features:

* Copy an album to Facebook
  See https://developers.facebook.com/docs/graph-api/reference/v2.1/user/albums#publish
  and https://developers.facebook.com/docs/graph-api/reference/v2.1/album/photos#publish 
* Link to social shared copies of photos/albums, maybe even showing the discussions inline?
* Agent for organizing photos--probably a separate service from the gallery.
    - EXIF editor
    - Set Google Drive description or title to EXIF/IPTC description or title, or vice versa.
    - Set created time based on EXIF data or vice versa.
    - Hazel-like features: organize incoming photos by date and/or exif data (date, location, which camera took it, etc.)
    - Auto-tag with face recognition
* Notification service: Let users subscribe to get an email when a gallery is updated (ex. for Grandma).
* Make a view for showing recently updated photos.
* Search within gallery

Notes on EXIF data:

* The first time an image is uploaded to Google Drive or Google+, if an Exif "Description" field is set, the description will be set to that in the Google database. But changing the EXIF description field later has no effect. (Moving an image within Google Drive doesn't cause it to be re-uploaded, so it Google won't update its description field then either.)

Notes on using Google Drive:

* To store the same file in multiple folders in the "new drive" UI, select the file and press shift-Z.
  You'll get an "Add to" dialog (instead of "Move to").
  Thanks to http://www.alicekeeler.com/teachertech/2014/07/11/new-google-drive-saving-a-document-in-multiple-folders/

* Keyboard shortcuts
  In Google Drive, press shift-/ to see a list of available keyboard shortcuts.
  Also see https://support.google.com/drive/answer/2563044?hl=en

