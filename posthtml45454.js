module.exports = {
  xmlMode: true,
  "root": "./src",
  "input": "*.html",
  "output": "public",
  "options": {
    "sync": true,
    "directives": [{"name": "?php", "start": "<", "end": ">"}]
  },
  "plugins": {
    "posthtml-plugin-name": {
      "property": "value"
    }
  }
};