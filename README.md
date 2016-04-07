geomag-edge-ws
==============

Web service for geomagnetism data stored in the EDGE.
(via the waveserver GETSCNLRAW interface)

# Getting started
- Make sure `node`, `npm`, and `grunt-cli` are installed.
- Make sure the project has been configured using pre-install, use the default values:
```
cd geomag-edge-ws
src/lib/pre-install
npm install
```
- Use grunt to start a local development server:
```
cd geomag-edge-ws
grunt
```


# An example request
http://127.0.0.1:8200/ws/edge/?starttime=2015-07-01T00:01:00Z&endtime=2015-07-01T00:05:00Z&station=BOU&network=NT&channel=MVH&location=R0
