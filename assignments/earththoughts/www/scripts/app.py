from flask import Flask, url_for
from flask import request
from flask_cors import CORS
import json
from flask import jsonify
import random


app = Flask(__name__)
CORS(app)

def has_no_empty_params(rule):
    defaults = rule.defaults if rule.defaults is not None else ()
    arguments = rule.arguments if rule.arguments is not None else ()
    return len(defaults) >= len(arguments)

@app.route('/', methods=['GET'])
def index():
    return site_map()


@app.route('/site-map', methods=['GET'])
def site_map():
    links = []
    for rule in app.url_map.iter_rules():
        # Filter out rules we can't navigate to in a browser
        # and rules that require parameters
        if "GET" in rule.methods and has_no_empty_params(rule):
            url = url_for(rule.endpoint, **(rule.defaults or {}))
            if not url == '/site-map':
                links.append((url, rule.endpoint))
    # links is now a list of url, endpoint tuples
    if len(links) > 0:
        return jsonify({"success": True,"routes":links})
    else:
        return jsonify({"success": False,"routes":links})


@app.route('/get_image')
def get_image():
    # grabs the image json file and extracts all images into a list.
    # the list suffles and we return the image at the list start.
    image_list = []
    jsondata = json.loads(open('earthporn.json','r').read())
    for child in jsondata['data']['children']:
        image_list.append(child['data']['preview']['images'][0]['source']['url'])
    random.shuffle(image_list)
    return jsonify({"success":True,"url":image_list[0]})
    
@app.route('/get_thought')
def get_thought():
    # grabs the image json file and extracts all images into a list.
    # the list suffles and we return the image at the list start.
    thought_list = []
    jsondata = json.loads(open('showerthoughts.json','r').read())
    for child in jsondata['data']['children']:
        thought_list.append(child['data']['title'])
    random.shuffle(thought_list)
    return jsonify({"success":True,"url":thought_list[0]})


if __name__ == '__main__':
    app.run(host='0.0.0.0', debug=True, port=5050)