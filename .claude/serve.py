import os, http.server, socketserver

DIRECTORY = "/Volumes/Datos/davidledesma/Documents/Claude/Projects/Estimate calculator V2"
os.chdir(DIRECTORY)

class Handler(http.server.SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=DIRECTORY, **kwargs)

with socketserver.TCPServer(("127.0.0.1", 8753), Handler) as httpd:
    httpd.serve_forever()
