let cookieValue = {
    "consents": {
    "essential": [
      "borlabs-cookie",
      "google-tag-manager"
    ]
  },
  "domainPath": "www.preventicus.com/",
  "expires": "Thu, 27 Oct 2022 10:11:31 GMT",
  "uid": "anonymous",
  "version": "1"
};

let cookieJSON = JSON.stringify(cookieValue);

let date = new Date();
let months = 3;
date.setTime(date.getTime() + (months*30*24*60*60*1000));
let expires = date.toUTCString();

document.cookie = `borlabs-cookie=${cookieJSON}; expires=${expires}; domain=localhost; path=/`;