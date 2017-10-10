import requests
import json
from flask import Flask, url_for, request, jsonify, Session
from flask_cors import CORS
import random
import os 
from pymongo import MongoClient
import time
import hashlib
from meme import create_meme

BLANKDIR = '/var/www/html/memes_blank'
MEMEDIR = '/var/www/html/memes'

# Create a link to our mongo database
client = MongoClient()

# Flask Quickstart Guide:
# http://flask.pocoo.org/docs/0.12/quickstart/

app = Flask(__name__)
CORS(app)

######################################################################
# Helper methods
######################################################################
def has_no_empty_params(rule):
    """
    Used in the "site_map" function
    """
    defaults = rule.defaults if rule.defaults is not None else ()
    arguments = rule.arguments if rule.arguments is not None else ()
    return len(defaults) >= len(arguments)

def duplicate_value(field_name,new_val):
    """
    field_name = key item in databaes were checking against (e.g. email, username)
    new_val = new item that needs to be unique
    """
    results = client['memes_db']['users'].find()

    for r in results:
        if field_name in r:
            if r[field_name] == new_val:
                return True
    return False


@app.route('/', methods=['GET'])
def index():
    """
    This "root" end point calls the "site_map" route.
    """
    return site_map()

@app.route('/site_map', methods=['GET'])
def site_map():
    """
    This is the route that displays each end point.
    """
    links = {}
    methods = ["DELETE", "GET", "PATCH", "POST", "PUT"]
    for rule in app.url_map.iter_rules():
        # Filter out rules we can't navigate to in a browser
        # and rules that require parameters

        for m in methods:
            if m in rule.methods and has_no_empty_params(rule):
                if not m in links:
                    links[m] = []
                url = url_for(rule.endpoint, **(rule.defaults or {}))
                links[m].append((url, rule.endpoint))

    # links is now a list of url, endpoint tuples
    if len(links) > 0:
        return jsonify({"success": True, "routes": links})
    else:
        return jsonify({"success": False, "routes": links})

# End Helper methods##################################################

# Mongo Help
# 
# Add items to mongo (POST):
#     Insert an item with an _id of 1:
#     _id = client['memes_db']['users'].insert({"_id":"1","other":"stuff"})
#
# Find items (GET):
#     Find items with an _id of 1:
#     res = client['memes_db']['users'].find({"_id":"1"})
#
# Get the count of a result:
#     Get the count of records with email of bob@google.com
#     count = client['memes_db']['users'].find({"email":"bob@google.com"}).count()
#
# For more go to: 
#     http://api.mongodb.com/python/current/api/pymongo/collection.html

# Request Help
#
# Figure out what kind of request it is:
#    request.method == POST, GET, PUT, DELETE
# 
# If the request.method is POST params will be in:
#    request.form['var1']
#    request.form['var2']
# If its a POST and your uploading a file:
#     request.files['the_file']
# If the request.method is GET params will be in:
#    request.args['var1']
#    request.args['var2']


######################################################################
# Begin Routes
######################################################################

# User Routes ########################################################

"""
POST    /user/new    : Add a new user to the site
POST    /user/find   : Retrieve (authenticate) user
PUT     /user/edit :  Update an existing user (not implementing right now)
"""

@app.route('/user/<action>', methods=['POST','DELETE'])
def user(action=None):
    print(action)
    """
    type: POST
    description:
        - Add a new user into mongo db collection called 'users'.
        - First check to see if an existing user is present before
          adding to db (based on email).
    params:
        first
        last
        email
        username
        password
    --------------------------------------------
    type: GET
    description:
        - Find an existing user from the mongo collection 'users'.
        - First check to see if an existing user is present before
          adding to db (based on email).
    params:
        first
        last
        email
        username
        password
    """
    unique_vals = ['email','username']
    if action == 'new':
        # create empty document 
        document = {}

        count = client['memes_db']['users'].find().count()
        max_id = count + 1

        # build document to insert
        for k,v in request.form.items():
            # check for duplicate values
            if k in unique_vals and duplicate_value(k,v):
                return jsonify({"success":False,"error":"duplicate value","key":k,"value":v})
            document[k] = v
        document['_id'] = max_id

        document['password'] = hashlib.sha224(document['password']).hexdigest()

        print(document)

        _id = client['memes_db']['users'].insert(document)

        if type(_id) is int:
            return jsonify({"success":True})
        else:
            return jsonify({"success":False,"error":"mongo error?"}) 

        # _id = client['memes_db']['users'].insert(document)
    elif action == 'find':
        """
        if username or email are equal to existing values, and password is equal to existing value return success
        otherwise return false
        """
        document = {}

        user_password = hashlib.sha224(request.form['password']).hexdigest()

        if request.form.has_key("email"):
             email_exists = client['memes_db']['users'].find({"email":request.form['email']}).count()
        else:
            email_exists = 0

        if request.form.has_key("username"):
            user_exists = client['memes_db']['users'].find({"username":request.form['username']}).count()
        else:
            user_exists = 0

        if email_exists + user_exists == 0:
            return jsonify({"success":False,"error":"User doesn't exists"})
        elif email_exists > 0:
            db_password = client['memes_db']['users'].find({"email":request.form['email']},{"_id":0,"password":1})
        else:
            db_password = client['memes_db']['users'].find({"username":request.form['username']},{"_id":0,"password":1})

        if 'password' in db_password[0]:
            print(db_password[0]['password'])

            # check hashed passwords match here ... 
            # if not, redirect to 

        return jsonify({"success":True})
    else: # its a delete
        pass


########################## Meme Routes ###############################
"""
POST /image : Add a new meme blank image to the db
GET  /image : Retreive a blank meme from the db
POST /meme: Add a new meme to the db
GET  /meme : Retreive a meme from the db
"""

@app.route('/image', methods=['POST','GET','DELETE'])
def image():
    """
    Meme Image Document:
    {
        "_id" : 6,
        "abs_path" : "/var/www/html/memes_blank/",
        "tags" : ['funny','pixie','dust','robert','downey'],
        "file_name" : "not_gay.jpg",
        "rel_path" : "memes_blank/",
        "owner_id" : -1
    }
    type: POST
    description:
        - Add a new image into mongo db collection called 'images' and to filesystem.
        - Do we check to see if image already exists?
        - Use example document above to help guide 
    params:
        - name (name of image)
        - description (if any)
        - url OR file (if was uploaded by user) resource
        - userid of owner ('-1' otherwise)
    --------------------------------------------
    type: GET
    description:
        - Find an existing image(s)
    params:
        - key(s) used to perform search
    """
    pass

@app.route('/meme', methods=['POST','GET','DELETE'])
def meme():
    """
    Meme Document:
    {
        "_id" : 3,
        "top_text" : "You smell good",
        "bot_text" : "when your sleeping...",
        "style_info" : {
            "text-color" : [
                0,
                0,
                0
            ],
            "text-size" : 24,
            "font" : blahblah.ttf,
            "etc" : .....
        },
        "file_id" : 5,
        "owner_id" : 2
    }
    type: POST
    description:
        - Add a new meme to the 'memes' collection.
    params:
        - _id of image
        - description (if any)
        - tags (list of keywords)
        - userid or email of owner ('anonymous' otherwise)
    --------------------------------------------
    type: GET
    description:
        - Find an existing meme from the mongo collection 'memes'.
    params:
        - _id of image
        - tag (key word search)
        - userid or email of owner ('anonymous' otherwise)
    """
    if request.method == 'POST':
        memeid = client['memes_db']['memes'].count() + 1
        img = {}
        img["_id"] = memeid
        for k,v in request.form.items():
            img[k] = v
        client['memes_db']['memes'].insert(img)
        return jsonify(img)
    pass

if __name__ == '__main__':
    app.run(host='0.0.0.0', debug=True, port=5050)
