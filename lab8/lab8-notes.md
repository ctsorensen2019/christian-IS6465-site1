# Lab 8 Notes: Laravel MVC Demo

**Name:** Christian Sorensen
**Codespaces link:** https://github.com/codespaces/orange-rotary-phone-4xvg64p76x43q45
**GitHub Link:** https://github.com/ctsorensen2019/christian-IS6465-site1/tree/main/lab8

*Note: The template for this md file was created by AI based on the instructions in assignment submission and based off of the files for this week's module. When I cloned the repository, it would not upload to GitHub since it was a clone. You may find that the "peer-tutoring-laravel-demo" folder does not work in the GitHub Link, but the images are located within it.
---

## Part 1: Run the Application

- [ X ] Created a GitHub Codespace from the starter repository
- [ X ] Followed the README setup instructions
- [ X ] Started the Laravel application and opened it in the browser
- [ X ] Completed the README walkthrough
- [ X ] Viewed and used the main Peer Tutoring Hub workflow

---

## Part 2: Explore MVC

### 1. What business purpose does the Peer Tutoring Hub application support?

The business need is to provide the students a place where they can submit tutoring requests and view the ones they have submitted. This will allow the students to both request help as well as see their upcoming tutoring sessions that they have requested. The business purpose is to support that need. There is no log in, though.

### 2. Identify one route used by the application. What URL or user action causes that route to run?

**Route:** requests.store

**URL or user action:** When the user submits a session request through the request page. The form sends a POST request to /requests and the route passes it to the TutoringRequestController


### 3. Identify the controller involved in one application workflow. What is the controller responsible for doing?

**Controller:** TutoringRequestController

**Workflow:** The tutoring request workflow.

**Responsibilities:** In the store() method, it first validates the submitted form. It creates the request when the user submits it. It stores the data for the submitted tutoring request so that it can be viewed in the web app later. After both of those things, it redirects the user to a confirmation page where it shows the submitted request.



### 4. Identify one model used by the application. What business data does it represent?

**Model:** TutoringRequest

**Business data:** It represents one tutoring request that is stored in the tutoring_requests table in the database.



### 5. Identify one view or Blade template. What does the user see or do on that page?

**View:** create.blade.php

**What the user sees or does:** This template shows the request form for the tutoring submission page. It shows all the fields the form allows for submission.



### 6. Explain how a request moves through the MVC pattern for one workflow in the application.

**Workflow:** Tutoring Request Submission

- **Route:** requests.store
- **Controller:** TutoringRequestController
- **Model or database interaction:** TutoringRequest
- **View returned to the user:** show.blade.php

**Explanation:**
The user clicks the "Request a session" button on the home page upon loading the page. Then the user is shown the "create.blade.php" view with the submission form. When the required fields are filled in and the form submitted by clicking "Request session", the user calls the route "requests.create", which then calls the request.store route and calls the store part of the TutoringRequestController and the "TutoringRequest" model. The request is stored in the database, and the user is shown a confirmation page with the requested session via show.blade.php.


### 7. Compare Laravel's MVC organization with the architecture used in your course project.

**Comparison:** It is vastly different. The course project currently does not have a location to store the database information, templates, or set workflow for how things should work.

**One benefit Laravel provides:** It is more organized, and easier to determine the workflow. This would be especially beneficial for troubleshooting.

**One reason we are not converting the project to Laravel at this point:** It makes more sense to not reinvent the wheel. What I mean by that is we have already done a lot of development on the course project, and it would be hard to create templates for grading since not everyone has the same project theme.



---

## Part 3: Evidence of Completion

### 1. Screenshot of the running Laravel application

images/lab8-running-app.png

### 2. One file from each MVC area (screenshot or short description)

**Route:** routes/web.php defines four routes. For example, Route::post('/requests', [TutoringRequestController::class, 'store'])->name('requests.store') sends a submitted form to the store() method in the controller. The file also redirects to the requests.

**Controller:** In TutoringRequestController.php, The store() method validates the input, saves it with TutoringRequest::create(), and redirects to the new request's details page.

**Model:** TutoringRequest.php extends Laravel's Model class. It stores the fields from the request form.

**View:** images/lab8-create.blade.php.png

### 3. Reflection

*What did Laravel make easier to understand or organize? What felt new or potentially confusing?*
The Controllers feel a bit confusing, it contains a lot of code that does not totally feel like PHP to me. Laravel makes the workflow of the user much easier to follow, and makes it easier to see what the app is doing behind the scenes.

