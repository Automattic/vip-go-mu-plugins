## Why might I want to customize how content is cached?
Web development, like anything, is often about balances and compromises. When it comes to serving dynamic content within a CMS, there are two ends of the spectrum: 
1.  Generate each page each time a user request comes. This is great for ensuring the user always has the most up-to-date copy but comes with a performance penalty as the code and database calls run each time.  
2. Generate a page once and then cache the content for future requests. This option offers fantastic performance but comes with the challenge requests displaying outdated content until it is regenerated manually or automatically

Thankfully most modern platforms including VIP Go deal with these trade-offs for you, ensuring a good mix of performance and up-to-date-content being served.  However, there are times when the caching behaviour needs to be customized for a particular use case. This is where the Cache Personalization module comes in.

There are three different approaches you can take to customize the caching depending on your use case:
1. No Cache(also known as cache-busting). THis is typically used for A, B, and C
2. Cache by Segment. This approach places each user into a "group" an serves up a variation of the content specific to that group. Examples of this are D, E, and F
3. Cache by authorization. This is like the above option except the group details are encrypted. This is useful if you are trying to to G, H, and I


## Example: A new beta feature on a post
In this example we will show how to add a like button(?) below a post to users who have chosen to view the beta site.


### Step 1: Register Groups

### Step 2: Assign the user into a the beta group when they opt-in

### Step 3: Show the custom beta feature 


### Complete example
