Link to repo: https://github.com/ctsorensen2019/christian-IS6465-site1/tree/main/lab4

**Part 1:**
After I submit the form, a simple confirmation page is displayed that lists out the details I put in the fields of the form.
Screenshot in the GitHub repository listed as "valid-submission-screenshot-part-1.png" in the "lab4" folder.

**Part 2:**
1. What content does header.php provide to every page?
header.php provides the style sheets and bootstrap to every page that calls it. It also gives the page title and other typical <head> information like the viewport and charset.

2. What content does nav.php provide?
It gives the navigation links to every page that calls it. Specifically the links to the requests.php and request-form.php pages. It also gives a title for the navbar div if it is called.

3. What content does footer.php provide?
It provides a container at the bottom of the pages that call it that show the title "Peer Tutoring Hub".

4. Why is require_once appropriate for these files?
It is because these files are not dynamically changing, and calling more than once would be pointless for them. The pages cannot run properly without the shared files.

5. Where is the shared CSS file linked?
The shared CSS file is linked in header.php.

**Part 3:**
The purpose of each control below and the type of value PHP receives after submission.
1. reason
This is so the user can dictate from 10 to 500 characters why they want tutoring. The PHP receives text after submission.

2. topics[]
It allows the user to only select from the list, but also allows them to select none of or up to all of the options. The PHP receives an array.

3. preferredtime
It allows the user to dictate what time given the radio buttons that allows for selection of a valid time. The PHP receives text.

4. preferreddate
It allows the user to dictate what date given the GUI that allows for selection of a valid date. The PHP receives a string.

5. form_name
It is a hi9dden field with the value tutoring_request. It tells which form the submission came from and makes sure the form submission is valid. It is a validation check submitted to the process-request.php to see if it equals tutoring_request. 

**Part 4:**
Complete and record the outcome of these three tests:

**Test**	                **What to do**
Valid request	                Select a valid course and time, enter a reason, choose at least one topic, and use today or a future date.
Missing or invalid value	Leave one required field incomplete or submit an invalid value.
Past date	                Select a date before today.
For each test, record:

**Valid request**
1. The values you entered
Exam review checked, IS 6465 selected, Time was Morning, Date was 11/14/2026, and the reason was "Tutoring for the students"
2. Whether the request was accepted or rejected
Accepted
3. The message displayed by the application
Request received
Course: IS 6465

Request details: Tutoring for the students

Topics: Exam review

Status: Pending review
4. The business rule being checked
Valid request

**Missing or invalid value**
1. The values you entered
Morning time and 11/14/2026 for preferred date were the only inputted fields
2. Whether the request was accepted or rejected
Rejected
3. The message displayed by the application
"Please select an item in the list." for the course
4. The business rule being checked
Missing or invalid value

**Past date**
1. The values you entered
Topics- Practice problems, Preferred time- Morning, Course- IS 6465, Preferred date- 10/14/2026, Reason- "What is going on"
2. Whether the request was accepted or rejectednumber f
Rejected
3. The message displayed by the application
Request could not be submitted
Preferred date cannot be in the past.
4. The business rule being checked
Past date

**Part 5:**
1. Open tests/validation-tests.php and record the result shown for the test cases.
All 10 test cases pass.
2. Open requests.php and then open one request details page.

3. Identify the page that represents each CRUD action.

CRUD action	     Page
Create	             request-form.php
Read one requests    request-details.php
Read all requests    requests.php
Update	             edit-request.php
Delete	             delete-request.php


Finally, answer these questions:

1. What query parameter appears in the request details URL?
request_id
2. Why is a GET query parameter appropriate for viewing one request?
It is for reading information, and viewing does not change any data. We want a read only query.
3. Why should an actual update or delete later use POST rather than GET?
An update or delete changes the state, and GET is read-only, while POST is modify.